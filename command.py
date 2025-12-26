import subprocess
import os
import datetime

# --- CONFIGURATION ---
CHANGELOG_FILE = "CHANGELOG.md"
REPO_PATH = "."

# --- SMART DICTIONARY (The Brain) ---
# This maps folder/filenames to readable context
CONTEXT_MAP = {
    "admin": "🛡️ Admin Panel",
    "css": "🎨 UI/UX Design",
    "style.css": "🎨 Visual Styles",
    "chat": "💬 Chat Engine",
    "upload": "📂 File System",
    "db.sql": "🗄️ Database Schema",
    "login": "🔐 Authentication",
    "register": "🔐 User Onboarding",
    "includes": "⚙️ Backend Config",
    "README.md": "📚 Documentation",
    "assets": "🖼️ Assets",
}

def run_command(command):
    """Runs a shell command and returns output."""
    result = subprocess.run(command, shell=True, capture_output=True, text=True)
    return result.stdout.strip()

def get_changes():
    """Detects modified, added, or deleted files."""
    output = run_command("git status --porcelain")
    if not output:
        return []
    
    changes = []
    for line in output.split("\n"):
        status = line[:2].strip()
        filepath = line[3:]
        filename = os.path.basename(filepath)
        
        # Determine Action
        action = "Update"
        if "A" in status or "?" in status: action = "New Feature"
        elif "D" in status: action = "Removed"
        elif "M" in status: action = "Enhanced"

        # Determine Context (Guessing work)
        context = "General"
        for key, value in CONTEXT_MAP.items():
            if key in filepath:
                context = value
                break
        
        changes.append(f"- **{context}:** {action} in `{filename}`")
    
    return changes

def update_changelog(changes):
    """Prepends new changes to the Changelog file."""
    if not changes:
        print("No changes to record.")
        return

    today = datetime.date.today().isoformat()
    header = f"\n## [Auto-Sync] - {today}\n"
    new_entry = header + "\n".join(changes) + "\n"

    # Read existing content
    if os.path.exists(CHANGELOG_FILE):
        with open(CHANGELOG_FILE, "r") as f:
            content = f.read()
    else:
        content = "# 🔄 Changelog\n\n"

    # Find where to insert (after the main header)
    insert_pos = content.find("\n## ")
    if insert_pos == -1:
        # If no previous versions, just append
        final_content = content + new_entry
    else:
        # Insert before the first version header
        final_content = content[:insert_pos] + new_entry + content[insert_pos:]

    with open(CHANGELOG_FILE, "w") as f:
        f.write(final_content)
    
    print(f"✅ Updated {CHANGELOG_FILE}")

def main():
    print("🤖 LazyBot is analyzing your work...")
    
    # 1. Get Changes
    changes = get_changes()
    if not changes:
        print("Everything is clean! Nothing to push.")
        return

    # 2. Update Changelog
    update_changelog(changes)

    # 3. Git Operations
    print("🚀 Pushing to GitHub...")
    run_command("git add .")
    
    # Create a summary commit message
    commit_msg = f"chore: Auto-update {len(changes)} files via LazyBot"
    run_command(f'git commit -m "{commit_msg}"')
    
    # Push
    push_output = run_command("git push origin main")
    print(push_output)
    print("✨ DONE! You can go back to sleep.")

if __name__ == "__main__":
    main()
