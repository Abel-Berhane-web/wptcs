<!--
Marp-compatible Markdown presentation slides
To convert to a PDF or dynamic slides, open in VS Code with the Marp extension or run Marp CLI.
-->

# 🎓 Web-Based Parent-Teacher Communication System (WPTCS)
## *Felege Tibeb Beata LeMariam Academy — Gondar, Ethiopia*
### 💡 Technical Presentation & System Walkthrough for Study Groups

---

# 📌 SLIDE 1: Project Overview
## The Ultimate Bridge Between School & Home

```
  ┌─────────────────────────────────────────────────────────────┐
  │                           WPTCS                             │
  │     A secure, monolithic, custom-engineered MVC portal      │
  │  supporting English & Amharic (አማርኛ) to optimize learning  │
  └──────────────────────────────┬──────────────────────────────┘
                                 ▼
         ┌───────────────────────┼───────────────────────┐
         ▼                       ▼                       ▼
 ┌──────────────┐        ┌──────────────┐        ┌──────────────┐
 │ ADMINISTRATIVE│        │  ACADEMIC    │        │ REAL-TIME    │
 │ AUTOMATION   │        │  EXCELLENCE  │        │ ENGAGEMENT   │
 └──────────────┘        └──────────────┘        └──────────────┘
```

### Key Mission:
*   **Centralize** academic records, continuous assessments, and daily attendance.
*   **Empower** teachers with automated homeroom and subject management.
*   **Provide** parents with immediate transparency into grades, evaluations, and attendance.

---

# 📌 SLIDE 2: Core Architecture
## High-Performance Monolithic MVC (No Framework Bloat)

```
                 [ 🌐 USER REQUEST ]
                         │
                         ▼
             ┌──────────────────────┐
             │      index.php       │ ◄─── Central front controller router
             └──────────┬───────────┘
                        │
         ┌──────────────┼──────────────┐
         ▼              ▼              ▼
   ┌───────────┐  ┌───────────┐  ┌───────────┐
   │  Config   │  │ Sessions  │  │ Helpers   │ ◄── Handles database singletons,
   │  & SQL    │  │  & Auth   │  │ & Mailer  │     security, and CSRF filters
   └───────────┘  └───────────┘  └───────────┘
                        │
                        ▼
             ┌──────────────────────┐
             │    Router Match      │
             └──────────┬───────────┘
                        │
         ┌──────────────┼──────────────┐
         ▼              ▼              ▼
   ┌───────────┐  ┌───────────┐  ┌───────────┐
   │   Admin   │  │  Teacher  │  │  Parent   │ ◄── Role-based modular folders
   └───────────┘  └───────────┘  └───────────┘
```

### Architectural Perks:
*   **Extremely Lightweight**: Runs flawlessly on basic standard servers.
*   **Strict Security Middleware**: Evaluates user authority on every request.
*   **Singleton Database Pool**: Ensures only one active connection per load cycle.

---

# 📌 SLIDE 3: Visual Database Relational Map
## Core Entity Relationships (InnoDB Integrity)

```
   ┌──────────────────┐               ┌──────────────┐               ┌───────────────┐
   │  academic_years  │◄───( 1 : N )──┤   sections   │───( N : 1 )──►│    grades     │
   └────────┬─────────┘               └──────┬───────┘               └───────────────┘
            │                                │
            │                                │ ( 1 : N )
            │                                ▼
            │                         ┌──────────────┐               ┌───────────────┐
            │                         │   students   │◄──( N : 1 )───┤     users     │
            │                         └──────┬───────┘               │ (Admin/Parent/│
            │                                │                       │ Teacher/Staff)│
            │                                │                       └───────┬───────┘
            │                                │ ( 1 : N )                     │
            ▼                                ▼                               │
   ┌─────────────────────────────────────────────────┐                       │
   │                      marks                      │◄───────( 1 : N )──────┘
   │  (Student, Subject, Assessment, Year, Semester) │
   └─────────────────────────────────────────────────┘
```

### Key Rules:
*   **Foreign Keys**: Explicit referential constraints prevent orphaned child records.
*   **Cascading Rules**: Automatically wipes matching marks/attendance if a student is removed.
*   **Optimized Indexing**: Database indexing on query variables guarantees fast loads.

---

# 📌 SLIDE 4: User Roles & Target Interfaces
## Custom Workflows for Every Stakeholder

| Role | Core View / Action | Secondary Action | Dynamic Helper |
| :--- | :--- | :--- | :--- |
| **👑 Admin** | User CRUD & CSV Import | Promotions & System Logs | Cryptographic Password Gen |
| **🎓 Principal** | School-Wide Academic Reports | Publish General Announcements | Performance Metric Aggregator |
| **👩‍🏫 Teacher** | Mark Continuous Assessments | Weekly Behavior Checklists | 48-Hour Edit Window Lock |
| **👨‍👩‍👦 Parent** | Child Dashboard & Grade View | Real-Time Chat & Weekly Cards | Automatic Email Alerts |

### Ultimate Benefit:
No user sees irrelevant features. The interface stays clean, simple, and role-appropriate.

---

# 📌 SLIDE 5: Bilingual Localization Engine
## Seamless On-The-Fly Language Switching

```
              ┌───────────────────────────┐
              │ User Clicks Switch Button │
              └─────────────┬─────────────┘
                            │
                            ▼
        ┌───────────────────────────────────────┐
        │  System Checks URL Parameter (?lang=)  │
        └─────────────┬───────────┬─────────────┘
                      │           │
            English   │           │   Amharic
                      ▼           ▼
        ┌────────────────┐     ┌────────────────┐
        │ Load lang/en.php│     │ Load lang/am.php│
        └────────┬───────┘     └────────┬───────┘
                 │                      │
                 └──────────┬───────────┘
                            │
                            ▼
      ┌───────────────────────────────────────────┐
      │ Caches array statically inside helper __()│
      └───────────────────────────────────────────┘
```

### Technical Perks:
*   **State Persistence**: Selected preferences are saved inside the logged-in user database record.
*   **Bilingual Header Support**: Automatically toggles page layouts and fonts to support standard Ethiopic script displays.

---

# 📌 SLIDE 6: Academic & Grading Engine
## Rigid, Secure, and Automated Continuous Assessments

```
  ┌───────────────────────────────────────────────────────────┐
  │                  COMPUTING WEIGHTED TOTALS                │
  │                                                           │
  │   Weight = Assessment Type Weight Value (e.g. 30.00%)     │
  │   Score  = Entered Score Value                            │
  │                                                           │
  │   Weighted Total = (Entered Score / Max Score) * Weight   │
  └─────────────────────────────┬─────────────────────────────┘
                                ▼
  ┌───────────────────────────────────────────────────────────┐
  │                 THE 48-HOUR SECURITY TIMER                │
  │                                                           │
  │  Checks: Date Entered + 48 Hours > Current Local Time    │
  │                                                           │
  │      [ YES ] ──► Edits Allowed (Active UI triggers)       │
  │      [  NO  ] ──► Grade LOCKED (Admin Intervention Only)   │
  └───────────────────────────────────────────────────────────┘
```

---

# 📌 SLIDE 7: Homeroom Weekly Behavior Reports
## Detailed Behavior Evaluation (17 Core Categories)

```
  ┌──────────────────────────────────────────────────────────┐
  │                 THE 17 BEHAVIOR METRICS                  │
  │                                                          │
  │  • Punctuality        • Active Participation  • Hygiene  │
  │  • Homework Completion • Neatness              • Honesty  │
  │  • Cooperation        • Conduct               • Respect  │
  │  ... and 8 additional tailored child character columns.   │
  └────────────────────────────┬─────────────────────────────┘
                               ▼
  ┌──────────────────────────────────────────────────────────┐
  │                  THE 5-STAR SCORE SCALE                  │
  │                                                          │
  │    [★☆☆☆☆] ──► Needs Attention                           │
  │    [★★★☆☆] ──► Meets Core Requirements                   │
  │    [★★★★★] ──► Excellent Progress                        │
  └────────────────────────────┬─────────────────────────────┘
                               ▼
  ┌──────────────────────────────────────────────────────────┐
  │      Encoded as dynamic JSON directly into DB record      │
  └──────────────────────────────────────────────────────────┘
```

---

# 📌 SLIDE 8: Real-Time Chat & Communications Engine
## Instant 3-Second AJAX Polling Flow

```
   TEACHER PORTAL                                         PARENT PORTAL
   ┌────────────┐                                         ┌────────────┐
   │            ├────── 1. Submits AJAX Comment ─────────►│            │
   │            │          (Sanitized, CSRF validated)    │  Displays  │
   │   Active   │◄───── 2. Confirms Dynamic Success ──────┤   Bubbles  │
   │    Chat    │                                         │  Instantly │
   │   Window   ├────── 3. Queries poll_comments?since ──►│            │
   │            │          (Executes every 3 seconds)     │            │
   │            │◄───── 4. Returns JSON payload values ───┤            │
   └────────────┘                                         └────────────┘
```

### System Integrity:
*   **Automatic Unread Counters**: Tracks reading state and updates red notification indicators on sidebars.
*   **Student-Centric Isolation**: All conversations are locked to target students, ensuring teachers and parents communicate contextually.

---

# 📌 SLIDE 9: Central Mailer & Notification System
## Direct SMTP Delivery via Google Integration

```
  ┌──────────────────┐
  │   System Event   │ ─── (Welcome Account / Recovery Request)
  └────────┬─────────┘
           │
           ▼
  ┌──────────────────┐
  │ PHPMailer Call   │ ◄─── Loads library dynamically
  └────────┬─────────┘
           │
           ▼
  ┌──────────────────┐
  │ Google SMTP Host │ ◄─── Direct TLS encryption on port 587
  └────────┬─────────┘
           │
           ▼
  ┌──────────────────┐
  │ Recipient Email  │ ─── Receives beautiful HTML template
  └──────────────────┘
```

### Key Integrations:
*   **Central Function**: Managed via `sendMail()` to keep settings DRY.
*   **App Passwords**: Uses Google secure passwords to bypass security roadblocks.

---

# 📌 SLIDE 10: Multi-Layer Security Architecture
## Safeguarding Educational Integrity

```
   ┌────────────────────────────────────────────────────────┐
   │              ROLE-BASED FILTER MIDDLEWARE              │
   │  Checks user role parameter before loading physical    │
   │  modular pages. Blocks lateral directory jumps.        │
   └───────────────────────────┬────────────────────────────┘
                               ▼
   ┌────────────────────────────────────────────────────────┐
   │             BRUTE-FORCE LOCKOUT PROTECTIONS            │
   │  Tracks failed password entries. Automatically locks   │
   │  accounts for 15 minutes after 5 consecutive failures.  │
   └───────────────────────────┬────────────────────────────┘
                               ▼
   ┌────────────────────────────────────────────────────────┐
   │             STATE CHANGE AUDIT LOG ENGINE              │
   │  Saves user details, actions, IP address, and JSON    │
   │  snapshots of old vs new database values.              │
   └────────────────────────────────────────────────────────┘
```

---

# 📌 SLIDE 11: Bulk Data Migration (CSV Import/Export)
## Onboard the Entire School in Seconds

```
  ┌─────────────────────────────────────────────────────────┐
  │                 ADMIN PREPARES CSV ROSTER               │
  │  Fields: First Name, Last Name, Phone, Email, Username  │
  └────────────────────────────┬────────────────────────────┘
                               ▼
  ┌─────────────────────────────────────────────────────────┐
  │                ADMIN UPLOADS CSV FILE                   │
  │  System runs parseCSVUpload(), verifying columns       │
  └────────────────────────────┬────────────────────────────┘
                               ▼
  ┌─────────────────────────────────────────────────────────┐
  │              TRANSACTIVE DB BULK ENROLL                 │
  │  • Creates parent user account                          │
  │  • Generates temporary password                         │
  │  • Enrolls child student record & links parent ID       │
  │  • Sends custom dynamic welcome credentials email       │
  └─────────────────────────────────────────────────────────┘
```

---

# 📌 SLIDE 12: Summary & Key Study Takeaways

### 💡 High-Performance Architecture
Monolithic design utilizing pure PHP and highly optimized database relations to guarantee fast execution without framework overhead.

### 🛡️ Secure by Design
Employs BCRYPT hashing, dynamic CSRF protections, brute-force limits, locked mark editing windows, and comprehensive audit logs.

### 🔄 Dynamic Collaboration
Combines a real-time polling chat engine, target-audience announcement modules, dynamic student-parent links, and Amharic translation engines to maximize school engagement.
