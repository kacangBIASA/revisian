pipeline {
  agent any

  environment {
    IMAGE_NAME = 'ridhoaja/queuenow-native'   // ganti username kamu
    REGISTRY_CREDENTIALS = 'dockerhub-credentials'
    CONTAINER_NAME = 'queuenow_app'
    APP_PORT = '8085'
  }

  stages {
    stage('Checkout') {
      steps { checkout scm }
    }

    stage('Build') {
      steps {
        bat 'docker version'
        bat "docker build -t %IMAGE_NAME%:%BUILD_NUMBER% ."
      }
    }

    stage('Push') {
      steps {
        withCredentials([usernamePassword(credentialsId: REGISTRY_CREDENTIALS,
          usernameVariable: 'USER', passwordVariable: 'PASS')]) {

          bat 'docker login -u %USER% -p %PASS%'
          bat "docker push %IMAGE_NAME%:%BUILD_NUMBER%"

          bat "docker tag %IMAGE_NAME%:%BUILD_NUMBER% %IMAGE_NAME%:latest"
          bat "docker push %IMAGE_NAME%:latest"
        }
      }
    }

    stage('Deploy Local (Windows)') {
      steps {
        bat "docker rm -f %CONTAINER_NAME% || exit /b 0"
        bat "docker pull %IMAGE_NAME%:latest"
        bat """
          docker run -d --name %CONTAINER_NAME% ^
            -p %APP_PORT%:80 ^
            %IMAGE_NAME%:latest
        """
      }
    }
  }
}
