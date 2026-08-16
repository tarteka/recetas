FROM node:24-alpine

WORKDIR /app

# Utiliza un directorio temporal escribible para la caché de npm.
ENV npm_config_cache=/tmp/.npm

COPY package.json package-lock.json ./

RUN npm install

# Mantiene Node como entorno de desarrollo del frontend.
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]