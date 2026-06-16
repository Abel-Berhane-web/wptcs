# Chapter 4: System Design

## 4.1 Introduction
The system design chapter translates the gathered requirements of the Web-Based Parent-Teacher Communication System (WPTCS) into a blueprint for the software architecture. This phase bridges the gap between problem domain requirements and the software solution. It defines the overall system architecture, data storage, user access controls, and the specific deployment strategies that will be utilized for Felege Tibeb Beata LeMariam Academy.

## 4.2 Current Software Architecture
Currently, Felege Tibeb Beata LeMariam Academy relies on traditional, paper-based communication methods, including physical report cards, communication books, and face-to-face meetings. There is no existing unified software architecture for parent-teacher interaction. The school uses standalone spreadsheet tools for administrative tasks, but these are not integrated into a cohesive, centralized architecture that allows for real-time data sharing between teachers, parents, and the administration.

## 4.3 Proposed Software Architecture
The proposed system adopts a **Layered (Modular) Web Architecture** pattern. This architecture separates the application into distinct logical layers:
1. **Presentation Layer (Client-Side):** Built using HTML5, CSS3, JavaScript, and Bootstrap 5. This layer manages the user interface and user interactions.
2. **Application/Business Logic Layer (Server-Side):** Developed using PHP 8. This layer processes user inputs, handles routing based on user roles, and executes core business rules (e.g., grading calculations, attendance tracking).
3. **Data Access Layer:** Utilizes PHP Data Objects (PDO) to securely interact with the database.
4. **Data Layer:** A MySQL relational database responsible for persistent data storage.

### 4.3.1 System Decomposition
To manage complexity, the WPTCS is decomposed into smaller, highly cohesive subsystems (modules) based on user roles and core functionalities:
* **Authentication Subsystem:** Handles secure user login, session management, and password recovery via encrypted email links.
* **Administrator Subsystem:** Manages system configuration, user accounts, academic years, subjects, sections, and teacher assignments.
* **Teacher Subsystem:** Allows teachers to enter marks, take daily attendance, submit weekly behavioral reports, and chat directly with parents.
* **Parent Subsystem:** Provides a dashboard for parents to view their children's academic performance, attendance records, weekly reports, and securely communicate with homeroom teachers.
* **Principal Subsystem:** Generates high-level statistical reports on overall school performance, teacher assignments, and grade-level averages.
* **Communication Subsystem:** A shared module managing bilingual (Amharic/English) direct messaging and school-wide announcements.

### 4.3.2 Hardware/Software Mapping
The system follows a standard Client-Server model. 

**Hardware Mapping:**
* **Server Node:** A cloud-based web server (e.g., InfinityFree) or a local host machine with sufficient RAM and storage to host the database and application files.
* **Client Nodes:** Personal computers, laptops, or mobile devices (smartphones/tablets) used by parents, teachers, and administrators. 

**Software Mapping:**
* **Server-Side:** Apache Web Server, PHP 8.2+ runtime environment, and MySQL Database Management System.
* **Client-Side:** Any modern web browser (Google Chrome, Mozilla Firefox, Safari, Edge). No additional software installation is required on the client side.

### 4.3.3 Persistent Data Modeling
The persistent data of the system is managed through a normalized relational database (`wptcs_db`). The core entities include:
* **Users:** Stores credentials, roles (`admin`, `principal`, `teacher`, `parent`), and contact information. Passwords are securely hashed using BCrypt.
* **Students:** Maintains student demographics and links them to their parents (via `parent_id`) and sections (`section_id`).
* **Academic Structure:** Managed through `academic_years`, `grades`, `sections`, and `subjects`.
* **Teacher Assignments:** The `teacher_subjects` table maps which teacher teaches which subject in a specific section.
* **Records:** Includes `marks` (academic scores), `attendance` (daily presence), and `weekly_reports` (behavioral metrics and character themes).
* **Communication:** Includes `announcements` (broadcasts) and `comments` (direct parent-teacher messaging).

### 4.3.4 Access Control and Security
Security is a critical component of the WPTCS architecture:
* **Role-Based Access Control (RBAC):** Users are strictly restricted to directories and actions corresponding to their roles (`requireRole()` function). Parents can only access data related to their mapped children.
* **Authentication:** Passwords are encrypted using PHP's native `password_hash()` (BCrypt). A lockout mechanism limits failed login attempts to prevent brute-force attacks.
* **Data Security:** All database interactions use prepared statements (PDO) to prevent SQL Injection. 
* **Session Security:** Sessions are securely managed and regenerated upon login.
* **Form Protection:** Cross-Site Request Forgery (CSRF) tokens are implemented on all POST forms to prevent unauthorized state-changing requests.

### 4.3.5 Detailed Class Diagram
*(Note: As the project uses a modular PHP approach rather than strict Object-Oriented Programming, the "Class Diagram" reflects the logical data entities and their relationships.)*
* **User Entity:** Attributes include `user_id`, `username`, `password`, `email`, `role`. Methods (Functions) include `authenticateUser()`, `hashPassword()`.
* **Student Entity:** Attributes include `student_id`, `parent_id`, `section_id`, `first_name`, `last_name`. 
* **Mark Entity:** Attributes include `mark_id`, `student_id`, `subject_id`, `score`. Methods include `calculateAverage()`, `getStudentMarks()`.
* **Attendance Entity:** Attributes include `attendance_id`, `student_id`, `date`, `status`. 
* **Report Entity:** Attributes include `report_id`, `student_id`, `metrics` (JSON), `character_theme`. 

*(In your final document, you should generate an actual UML Class or ER Diagram drawing illustrating these entities).*

### 4.3.6 Package Diagram
The system source code is organized into logical packages (directories) to ensure maintainability:
* **`/config`**: Contains database connection and environment variables.
* **`/includes`**: Contains reusable helper functions, session management, auth logic, and UI headers/footers.
* **`/lang`**: Contains localization packages (`en.php`, `am.php`) for bilingual support.
* **`/modules`**: The core package divided into sub-packages: `/admin`, `/teacher`, `/parent`, `/principal`, `/shared`, and `/auth`.
* **`/assets`**: Contains static resources like CSS, JavaScript files, and images.

### 4.3.7 Deployment
The deployment architecture is designed for a highly available, cloud-based environment.
* **Development Environment:** The system was developed and tested locally using a WAMP server stack (Windows, Apache, MySQL, PHP).
* **Production Environment:** The application files will be deployed to a cloud hosting provider (e.g., InfinityFree). 
* **Deployment Protocol:** Source code is transferred via FTP. The database schema (`schema.sql` and `seed.sql`) is imported into the production MySQL server via phpMyAdmin. 
* **Client Access:** End-users access the deployed system globally over the internet via HTTP/HTTPS protocols using their web browsers.
