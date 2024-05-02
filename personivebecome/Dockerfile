FROM ghost:5-alpine

WORKDIR /var/lib/ghost

RUN npm install ghost-storage-adapter-b2

RUN mkdir -p ./content/adapters/storage

RUN cp -r ./node_modules/ghost-storage-adapter-b2 ./content/adapters/storage/b2
