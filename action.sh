#!/bin/bash

# Errors:
#  1 REGISTRY not set
#  2 TARGETVERSION AND STRING AND COMMAND not set (one must be set)
#  3 TARGETVERSION not found (must be found with inspect with TARGETSTRING)
#  4 BASECONTAINER set, but not BASETYPE
#  5 "Dockerfile.${BASETYPE}" not found
#  6 TARGETBATCH result was empty
#  7 no ACTION type is set

set -x

if [ -z "${NAME}" ]; then
  NAME="${PWD##*/}"
fi

if [ -z "${REGISTRY}" ]; then
  exit 1
fi

if [ -z "${TARGETVERSION}" ]; then
 if [ -z "${TARGETSTRING}" ]; then
   if [ -z "${TARGETRUNVERSION}" ]; then
    exit 2
   fi
 fi
fi

if [ "${ACTION}" == "build" ] || [ "${ACTION}" == "all" ]; then
# Start of action "build"
  if [ -n "${BASECONTAINER}" ]; then
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

  docker pull `grep "^FROM " "${DOCKERFILE}" | cut -d" " -f2` && \
  docker build --no-cache --rm -t ${REGISTRY}/${NAME}:latest --file "${DOCKERFILE}" .

# End of action "build"
elif [ "${ACTION}" == "push" ] || [ "${ACTION}" == "all" ]; then
# Start of action "push"

  if [ -n "${TARGETRUNVERSION}" ]; then
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

  docker push ${REGISTRY}/${NAME}:latest && \
  docker tag  ${REGISTRY}/${NAME}:latest ${REGISTRY}/${NAME}:${TARGETVERSION} && \
  docker push ${REGISTRY}/${NAME}:${TARGETVERSION}

  if [ -n "${SECONDARYREGISTRY}" ]; then
    if [ -n "${SECONDARYNAME}" ]; then
      docker tag  ${REGISTRY}/${NAME}:latest ${SECONDARYREGISTRY}/${SECONDARYNAME}:latest && \
      docker push ${SECONDARYREGISTRY}/${SECONDARYNAME}:latest
      docker tag  ${REGISTRY}/${NAME}:${TARGETVERSION} ${SECONDARYREGISTRY}/${SECONDARYNAME}:${TARGETVERSION} && \
      docker push ${SECONDARYREGISTRY}/${SECONDARYNAME}:${TARGETVERSION}
    else
      docker tag  ${REGISTRY}/${NAME}:latest ${SECONDARYREGISTRY}/${NAME}:latest && \
      docker push ${SECONDARYREGISTRY}/${NAME}:latest
      docker tag  ${REGISTRY}/${NAME}:${TARGETVERSION} ${SECONDARYREGISTRY}/${NAME}:${TARGETVERSION} && \
      docker push ${SECONDARYREGISTRY}/${NAME}:${TARGETVERSION}
    fi
  fi
# End of action "push"
else
  # Error if no valid action was found
  exit 7
fi

exit 0
######