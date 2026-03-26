Update the wiki PRD after a feature ships or changes.

Arguments: $ARGUMENTS should contain: <wiki-file-path> [description of what changed]

Steps:
1. Read the specified wiki PRD file
2. Read the relevant code (controllers, services, routes, views) to understand current implementation
3. Compare the PRD's "Current" vs "Planned" sections against actual code
4. Move shipped items from "Planned" to "Current" with accurate descriptions
5. Update any outdated information (routes, models, workflows)
6. If new capabilities were added that aren't in the PRD, add them to "Current"
7. Update the wiki README.md index if module descriptions changed
8. Show a diff summary of what was updated
