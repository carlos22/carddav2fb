pipeline {
    agent none
    triggers {
	cron('H H(2-7) * * 3')
    }
    options {
//		skipStagesAfterUnstable()
	disableResume()
	timestamps()
    }
    environment {
	REGISTRY = "intrepidde"
    }
    stages {
	stage('Build rpi/arm32v6') {
	    agent {
		label 'arm32v6 && Docker'
	    }
	    environment {
		NAME = "carddav2fb"
		TARGETVERSION = "master"
		SECONDARYREPOSITORY = "intrepidde"
		SECONDARYNAME = "rpi-carddav2fb"
	    }
	    steps {
		checkout scm
		sh 'cp Docker/Dockerfile.rpi ./Dockerfile'
		sh './build.sh'
	    }
	}
	stage('Build x86') {
	    agent {
		label 'x86 && Docker && build-essential'
	    }
	    environment {
		NAME = "carddav2fb"
		TARGETVERSION = "master"
	    }
	    steps {
		checkout scm
		sh 'cp Docker/Dockerfile.x86 ./Dockerfile'
		sh './build.sh'
	    }
	}
    }
}