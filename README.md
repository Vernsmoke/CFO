# Christian Formation Office Management System

A local Next.js pilot for the University of La Salette, Inc. Christian Formation Office. It includes student profiles, activities, registrations, protected document uploads and review, announcements, staff attendance, facility requests, reports, and audit history.

## Run on another computer

Install Node.js 22.9 or newer (Node.js 24 recommended), npm, and Git.

```powershell
git clone https://github.com/Vernsmoke/CFO.git
cd CFO
npm ci
Copy-Item .env.example .env.local
```

Edit `.env.local` for a fictional local demonstration:

```dotenv
DEMO_MODE=true
DEMO_PASSWORD=choose-your-own-local-demo-password
```

Then initialize local records and start the app:

```powershell
npm run seed
npm run dev
```

Open the loopback URL printed in the terminal, normally `http://127.0.0.1:3000`. On macOS/Linux, use `cp .env.example .env.local` instead of `Copy-Item`. The other commands are the same.

Seeding creates fictional accounts using the password you selected and preserves existing records. Choose an account on the sign-in screen:

| Role | University ID Number |
| --- | --- |
| Student | `2024-0001` or `2024-0002` |
| CFO staff | `CFO-001` |
| Department representative | `DEP-001` |
| Facility approver | `APR-001` |
| Administrator | `ADM-001` |
| Aggregate report viewer | `VIEW-001` |

## Commands

- `npm run dev`: start the local development server.
- `npm run typecheck`: validate TypeScript.
- `npm run build`: create the production build.
- `npm run start`: serve an existing build locally.
- `npm run seed`: initialize fictional local records without replacing existing data.

## Storage and permissions

Local records are created in `data/cfo.json`; uploads are created in `private-uploads/`. Neither is distributed in this repository. Uploaded files are served only through authenticated, permission-checked routes. Passwords use salted scrypt, session tokens are hashed, and important actions are recorded in the audit history.

Students access their own submissions. CFO staff manage office workflows; administrators manage accounts. Department representatives request facilities, approvers decide requests, and viewers receive aggregate reports. Approved facility overlaps are blocked; pending conflicts remain for authorized review.

## Scope and limitations

This is a single-process local pilot with fictional data, not a production university deployment. Account provisioning, detailed attendance rules, facility authority, retention, hosting, and maintenance require university confirmation. Production requires transactional storage, private object storage, approved identity procedures, backups, and further security/privacy validation. Uploaded files are signature-checked, not malware-scanned; the JSON audit store is not tamper-proof.

Payments stay with the university cashier. Receipt review confirms document requirements only and never verifies a financial transaction. Official student records remain with the Registrar; no university database or authentication integration is assumed. Staff record attendance without student phones. Email and SMS are not configured.

The repository includes application source, required assets, configuration, a dependency lockfile, and the fictional seed script. Agent guidance/skills, local verification tools, credentials, records, uploads, screenshots, and generated build files are excluded.

## Asset sources

[Cabinet Grotesk by Fontshare](https://www.fontshare.com/fonts/cabinet-grotesk), [Lucide icons](https://lucide.dev/), and [Lorem Picsum photographs](https://picsum.photos/). Photographs and branding are illustrative; they are not official university assets.
