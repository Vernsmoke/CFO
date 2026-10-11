import { readFile } from "node:fs/promises";
import path from "node:path";
import { readDb, uploadDirectory } from "@/lib/store";

// Only the admin-selected public logo is served; private document IDs are never accepted.
export async function GET() {
  const db = await readDb();
  const logo = db.settings?.logo;
  if (!logo || !/^[a-f0-9-]{36}$/.test(logo)) return new Response("Not found", { status: 404 });
  try {
    const buffer = await readFile(path.join(uploadDirectory, logo));
    const mime = buffer[0] === 137 ? "image/png" : "image/jpeg";
    return new Response(buffer, {
      headers: {
        "Content-Type": mime,
        "Cache-Control": "no-store",
        "X-Content-Type-Options": "nosniff",
      },
    });
  } catch {
    return new Response("Not found", { status: 404 });
  }
}
