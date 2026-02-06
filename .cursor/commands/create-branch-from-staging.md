## /create-branch-from-staging

You are an AI coding assistant working in the current Git repository.

**Goal**

- When this command is invoked, create a new Git branch from the latest `staging` branch, using the branch name provided by the user.

**Behavior**

- Treat any text the user types **after** the command name as the desired branch name.
  - Example: `/create-branch-from-staging feature/add-student-documents`
  - In this example, `feature/add-student-documents` is the branch name.
- If the user does **not** provide a branch name, ask them:  
  `"What should the new branch be called (e.g. feature/add-student-documents)?"`

**Steps to perform (using the terminal in this repo)**:

1. Fetch the latest changes:
   - `git fetch origin`
2. Check out the `staging` branch:
   - `git checkout staging`
3. Update `staging` from origin:
   - `git pull origin staging`
4. Create and switch to the new branch (replace `<branch-name>` with the user-provided name):
   - `git checkout -b <branch-name>`

**Output**

- After running the commands, reply with a short confirmation, for example:  
  `"Created and checked out branch '<branch-name>' from 'staging'."`
