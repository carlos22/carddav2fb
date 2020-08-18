#!/bin/bash

# Errors:
#  1 REGISTRY not set
#  2 TARGETVERSION AND STRING AND COMMAND not set AND OVERRIDE still set to latest (one must be set)
#  3 TARGETVERSION not found (must be found with inspect with TARGETSTRING)
#  4 BASECONTAINER set, but not BASETYPE
#  5 "Dockerfile.${BASETYPE}" not found
#  6 TARGETBATCH result was empty
#  7 additional docker run failed
#  8 BASESCRIPT did run with error
#  9 docker build failed

if [ -n "${DEBUG}" ]; then
 set -x
fi

if [ -n "${OVERRIDE}" ]; then
  echo ":${OVERRIDE}:"
fi

if [ -z "${NAME}" ]; then
  NAME="${PWD##*/}"
fi

if [ -z "${REGISTRY}" ]; then
  exit 1
fi

if [ "${OVERRIDE}" != "latest" ] && [ -n "${OVERRIDE}" ] && [ -n "${SOFTWARESTRING}" ]; then
  SOFTWAREVERSION="${OVERRIDE}"
fi

if [ "${OVERRIDE}" != "latest" ] && [ -n "${OVERRIDE}" ]; then
  TARGETVERSION="${OVERRIDE}"
fi

###
# adding tagsuffix to tag "latest" before build
# will be added to TARGETVERSION tag during push phase
if [ -n "${TAGSUFFIX}" ]; then
 LATEST="latest${TAGSUFFIX}"
else
 LATEST="latest"
fi
###

if [ "${ACTION}" == "build" ] || [ "${ACTION}" == "all" ]; then
# Start of action "build"
  if [ -n "${BASECONTAINER}" ] && [ -z "${BASESCRIPT}" ]; then
    if [ -z "${BASETYPE}" ]; then
      exit 4
    else
      if [ -f "Dockerfile.${BASETYPE}" ]; then
        cp "Dockerfile.${BASETYPE}" "Dockerfile.${NAME}"
        DOCKERFILE="Dockerfile.${NAME}"
        sed -i s~"<<BASECONTAINER>>"~"${BASECONTAINER}"~g "${DOCKERFILE}"
      else
        exit 5
      fi
    fi
  elif [ -n "${BASESCRIPT}" ]; then
    DOCKERFILE="`/bin/bash ${BASESCRIPT}`"
    if [ $? -ne 0 ]; then
      exit 8
    fi
  else
    DOCKERFILE="Dockerfile"
  fi

  if [ -n "${RUNUSER}" ]; then
    sed -i s/"^#USER "/"USER "/g "${DOCKERFILE}"
    sed -i s/"<<RUNUSER>>"/"${RUNUSER}"/g "${DOCKERFILE}"
  fi

  if [ -n "${SOFTWAREVERSION}" ] && [ -n "${SOFTWARESTRING}" ]; then
    sed -i s~"${SOFTWARESTRING}"~"${SOFTWAREVERSION}"~g "${DOCKERFILE}"
  fi

  if [ -n "${SECONDARYSOFTWAREVERSION}" ] && [ -n "${SECONDARYSOFTWARESTRING}" ]; then
    sed -i s~"${SECONDARYSOFTWARESTRING}"~"${SECONDARYSOFTWAREVERSION}"~g "${DOCKERFILE}"
  fi

  for pull in $(grep "^FROM " "${DOCKERFILE}" | cut -d" " -f2)
  do
    docker pull $pull
  done

  if [ -n "${ADDITIONALCONTAINER}" ] && [ -n "${ADDITIONALCONTAINERSTRING}" ]; then
    sed -i s~"^FROM ${ADDITIONALCONTAINERSTRING}"~"FROM ${ADDITIONALCONTAINER} AS ${NAME}-transfer"~g "${DOCKERFILE}"
    sed -i s~"--from=${ADDITIONALCONTAINERSTRING}"~"--from=${NAME}-transfer"~g "${DOCKERFILE}"
  fi

  docker build --no-cache --rm -t ${REGISTRY}/${NAME}:${LATEST} --file "./${DOCKERFILE}" . || exit 9

fi
# End of action "build"

# Start of action "push"
if [ "${ACTION}" == "push" ] || [ "${ACTION}" == "all" ]; then
  if [ -z "${TARGETVERSION}" ] && [ -z "${TARGETSTRING}" ] && [ -z "${TARGETRUNVERSION}" ]; then
    exit 2
  fi

  if [ -n "${TARGETRUNVERSION}" ] && [ -z "${TARGETVERSION}" ]; then
    TARGETVERSION=`${TARGETRUNVERSION} ${REGISTRY}/${NAME}:latest`
    if [ -z "${TARGETVERSION}" ]; then
      exit 6
    fi
  fi

  if [ -z "${TARGETVERSION}" ]; then
    TARGETVERSION=`docker inspect ${REGISTRY}/${NAME} | grep -m1 "${TARGETSTRING}" | cut -d"\"" -f2 | cut -d"=" -f2`
    if [ -z "${TARGETVERSION}" ]; then
      exit 3
    fi
  fi

  docker tag  ${REGISTRY}/${NAME}:${LATEST} ${REGISTRY}/${NAME}:${TARGETVERSION}${TAGSUFFIX}
  if [ "${NOLATEST}" != "true" ]; then
    docker push ${REGISTRY}/${NAME}:${LATEST}
  else
    docker rmi ${REGISTRY}/${NAME}:${LATEST}
  fi
  docker push ${REGISTRY}/${NAME}:${TARGETVERSION}${TAGSUFFIX}

  if [ -n "${SECONDARYREGISTRY}" ]; then
    if [ -n "${SECONDARYNAME}" ]; then
      if [ "${NOLATEST}" != "true" ]; then
        docker tag  ${REGISTRY}/${NAME}:${LATEST} ${SECONDARYREGISTRY}/${SECONDARYNAME}:${LATEST} && \
        docker push ${SECONDARYREGISTRY}/${SECONDARYNAME}:${LATEST}
      fi
      docker tag  ${REGISTRY}/${NAME}:${TARGETVERSION}${TAGSUFFIX} ${SECONDARYREGISTRY}/${SECONDARYNAME}:${TARGETVERSION}${TAGSUFFIX} && \
      docker push ${SECONDARYREGISTRY}/${SECONDARYNAME}:${TARGETVERSION}${TAGSUFFIX}
    else
      if [ "${NOLATEST}" != "true" ]; then
        docker tag  ${REGISTRY}/${NAME}:${LATEST} ${SECONDARYREGISTRY}/${NAME}:${LATEST} && \
        docker push ${SECONDARYREGISTRY}/${NAME}:${LATEST}
      fi
      docker tag  ${REGISTRY}/${NAME}:${TARGETVERSION}${TAGSUFFIX} ${SECONDARYREGISTRY}/${NAME}:${TARGETVERSION}${TAGSUFFIX} && \
      docker push ${SECONDARYREGISTRY}/${NAME}:${TARGETVERSION}${TAGSUFFIX}
    fi
  fi
# End of action "push"
fi

exit 0
######