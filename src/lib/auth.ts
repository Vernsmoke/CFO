import "server-only";
import { cookies } from "next/headers";
import { redirect } from "next/navigation";
import { hashToken } from "./crypto";
import { readDb } from "./store";
import type { Role, User } from "./types";

export async function currentUser(): Promise<User | null> {
  const token = (await cookies()).get("cfo_session")?.value;
  if (!token) return null;
  const db = await readDb();
  const session = db.sessions.find(
    (s) => s.tokenHash === hashToken(token) && s.expiresAt > Date.now(),
  );
  return db.users.find((u) => u.id === session?.userId && u.active) ?? null;
}
export async function requireUser(roles?: Role[]) {
  const user = await currentUser();
  if (!user) redirect("/login");
  if (roles && !roles.includes(user.role))
    throw new Error("You do not have permission for this action.");
  return user;
}
