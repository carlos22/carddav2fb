pipeline {
    agent none
    triggers {
	cron('H H(4-12) * * 3')
    }
    options {
	disableResume()
	timestamps()
    }
    environment {
	REGISTRY = "intrepidde"
	SECONDARYREGISTRY = "nexus.intrepid.local:4000"
    }
    stages {
	stage('Build') {
	    parallel {
		stage('rpi/arm32v6') {
		    agent {
			label 'arm32v6 && Docker'
		    }
		    environment {
			NAME = "rpi-carddav2fb"
			SECONDARYNAME = "${NAME}"
			TARGETVERSION = "master"
			ACTION = "all"
		    }
		    steps {
			checkout scm
			sh 'cp Docker/Dockerfile.rpi ./Dockerfile'
			sh './action.sh'
		    }
		}
		stage('x86') {
		    agent {
			label 'x86 && Docker && build-essential'
		    }
		    environment {
			NAME = "carddav2fb"
			SECONDARYNAME = "${NAME}"
			TARGETVERSION = "master"
			ACTION = "all"
		    }
		    steps {
			checkout scm
			sh 'cp Docker/Dockerfile.x86 ./Dockerfile'
			sh './action.sh'
		    }
		}
	    }
	}
    }
}