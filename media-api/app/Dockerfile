# Use the official lightweight Node.js image
FROM node:18-slim

# Create app directory
WORKDIR /usr/src/app

# Copy package files and install dependencies
COPY package*.json ./
RUN npm install

# Copy the rest of your app source code
COPY . .

# Expose the port the app runs on
EXPOSE 3000

# Start the application
CMD [ "node", "server.js" ]