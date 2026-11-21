# The engine behind its small HTTP service, for the server and nobody else. Build context: the repository root.
# Node runs the TypeScript sources as they are (22.18 and later): no build step, no dependency to install.
FROM node:26.13.1-alpine

# Alpine's fixes published since the image above was built (a zlib update, the day this was written)
RUN apk upgrade --no-cache

WORKDIR /srv
# the bridge imports ../../engine/src: both keep the places they have in the repository
COPY engine/package.json engine/
COPY engine/src engine/src
COPY bridge/package.json bridge/
COPY bridge/src bridge/src

ENV NODE_ENV=production BRIDGE_HOST=0.0.0.0 BRIDGE_PORT=8787
USER node
EXPOSE 8787
CMD ["node", "bridge/src/main.ts"]
