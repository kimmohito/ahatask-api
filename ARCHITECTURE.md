Core Requiremen ts

## Data Model

- ✅ An Organization has many Users, Projects, and Tasks. 
  - ✅ User
  - ✅ Organization
  - ✅ Project
  - ✅ Task

- ✅ A Project belongs to one organization and has many tasks. 
    - ✅ Project belong to only 1 organization

- ✅ A Task belongs to a project, has a title, description, status, an optional assignee, and a created/updated timestamp. 
    - Multiple task belong to a project, if doesn't belog to any project, it will be viewable by other people in the same organisation
    - Techincally the View (Organization Members, Project Members, Private)

## Auth & roles

- ✅ Users log in and belong to exactly one organization.
  - Only 1 organization for each user 

- ✅ Two roles: Admin (manage users and projects), Member (create/edit tasks). 
  - admin
    - manage users (create, read, update, delete)
    - manage organisations (create, read, update, delete)
    - manage projects (create, read, update, delete)
    - manage task (create, read, update, delete)
  - user
    - create task
    - read task
    - edit task

- ✅ Enforce what each role can and cannot do. 

## API

- CRUD for projects and tasks.
- A task list endpoint that supports pagination, filtering (by status, assignee), and sorting — assume a project could have thousands of tasks. 
  - pagination
  - filtering
    - status
    - assignee
    - from-to-date
  - sorting

## Frontend

- Login screen. 
- A project view showing its tasks in a table or board, with the filtering/sorting/pagination above. 
- Create and edit tasks without full-page reloads. 
- Handle loading, empty, and error states; reflect the user's role in the UI. 

## Activity Tracking

- Record significant task-related events. 
    - Title
    - Reporter
    - Assignee
    - Priority (Critical, High, Medium, Low)
    - Tags
- Activity history should be viewable for auditing purposes.
  - Laravel Spatie Activity Logs implemented on
    - User History
    - Organization History
    - Project History
    - Task History

## Dashboard

Provide a dashboard that gives an organization visibility into its work, such as: 
- Workload overview 
  - Total Tasks
  - Total Pending Task
  - Total In progress Task
  - Total Critical / High Priority Task
- Task status distribution
- Team activity summary

The dashboard must only contain information from the current organization.

Constraints (these matter) 

- A user from Organization A must never be able to read or modify Organization B's data, through any endpoint. 
  - ✅ Added Multitenancy scope where different organization would never able to access different organization data model on backend

- The task list must stay responsive with thousands of tasks per project. 
    - 

- The UI must behave sensibly when the API is slow or returns an error. 
- Role permissions must be actually enforced, not just visually hidden. 
