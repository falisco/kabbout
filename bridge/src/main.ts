// `node bridge/src/main.ts`: starts the bridge. It listens on this machine only unless BRIDGE_HOST says otherwise.
import type { AddressInfo } from 'node:net';
import { createBridge } from './server.ts';

const host = process.env.BRIDGE_HOST ?? '127.0.0.1';
const port = Number(process.env.BRIDGE_PORT ?? 8787);

// port 0 asks the system for a free one: the line printed says which
const server = createBridge().listen(port, host, () =>
  console.log(`Kabbout bridge listening on http://${host}:${(server.address() as AddressInfo).port}`),
);
for (const signal of ['SIGINT', 'SIGTERM'] as const) process.on(signal, () => server.close(() => process.exit(0)));
