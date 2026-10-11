import { mkdir, readFile, rename, writeFile } from "node:fs/promises";
import path from "node:path";
import { randomUUID } from "node:crypto";
import type { Database, User } from "./types";

// Runtime records are initialized outside the application bundle, never traced into it.
const directory = path.resolve(/* turbopackIgnore: true */ process.env.CFO_DATA_DIR ?? "data");
export const uploadDirectory = path.resolve(
  /* turbopackIgnore: true */ process.env.CFO_UPLOAD_DIR ?? "private-uploads",
);
const file = path.join(directory, "cfo.json");
const state = globalThis as typeof globalThis & { cfoQueue?: Promise<unknown> };
export async function readDb(): Promise<Database> {
  try {
    return JSON.parse(await readFile(file, "utf8")) as Database;
  } catch (error) {
    if ((error as NodeJS.ErrnoException).code === "ENOENT")
      throw new Error("Pilot data is missing. Run npm run seed.", { cause: error });
    throw error;
  }
}
export async function mutateDb<T>(operation: (db: Database) => T | Promise<T>): Promise<T> {
  const task = (state.cfoQueue ?? Promise.resolve()).then(async () => {
    const db = await readDb();
    const result = await operation(db);
    await writeDatabase(db);
    return result;
  });
  state.cfoQueue = task.catch(() => undefined);
  return task;
}
export async function writeDatabase(db: Database) {
  await mkdir(directory, { recursive: true });
  const temporary = `${file}.${randomUUID()}.tmp`;
  await writeFile(temporary, JSON.stringify(db, null, 2), { mode: 0o600 });
  await rename(temporary, file);
}
export function audit(
  db: Database,
  user: Pick<User, "id">,
  action: string,
  target: string,
  detail = "",
) {
  db.audit.unshift({
    id: randomUUID(),
    actorId: user.id,
    action,
    target,
    detail,
    at: new Date().toISOString(),
  });
}
export function notify(db: Database, userId: string, message: string) {
  db.notifications.unshift({
    id: randomUUID(),
    userId,
    message,
    createdAt: new Date().toISOString(),
    read: false,
  });
}
