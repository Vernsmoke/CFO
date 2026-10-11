import { readFile } from "node:fs/promises";
import path from "node:path";
import { currentUser } from "@/lib/auth";
import { audit, mutateDb, readDb, uploadDirectory } from "@/lib/store";
export const runtime = "nodejs";
export async function GET(_request: Request, { params }: { params: Promise<{ id: string }> }) {
  const user = await currentUser();
  if (!user) return new Response("Unauthorized", { status: 401 });
  const { id } = await params;
  const db = await readDb();
  const document = db.documents.find((d) => d.id === id);
  if (
    !document ||
    !(user.role === "staff" || (user.role === "student" && document.userId === user.id))
  )
    return new Response("Not found", { status: 404 });
  if (!/^[a-f0-9-]{36}$/.test(document.storageName))
    return new Response("Invalid document", { status: 400 });
  try {
    const buffer = await readFile(path.join(uploadDirectory, document.storageName));
    await mutateDb((current) => audit(current, user, "Document downloaded", id, document.filename));
    return new Response(buffer, {
      headers: {
        "Content-Type": document.mime,
        "Content-Disposition": `attachment; filename*=UTF-8''${encodeURIComponent(document.filename)}`,
        "Cache-Control": "private, no-store",
        "X-Content-Type-Options": "nosniff",
      },
    });
  } catch {
    return new Response("Document unavailable", { status: 404 });
  }
}
