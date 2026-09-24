#!/bin/bash
# TNTT Agent Team Workflow Script

set -e

echo "=== TNTT Agent Team Workflow ==="
echo ""

# Check if on a feature branch
BRANCH=$(git branch --show-current)
if [ "$BRANCH" == "master" ] || [ "$BRANCH" == "main" ]; then
    echo "⚠️  WARNING: You are on $BRANCH branch!"
    echo "   Create a new branch before making changes:"
    echo "   git checkout -b feat/[feature-name]"
    echo ""
fi

echo "Current branch: $BRANCH"
echo ""
echo "Available agents:"
echo "  - analyzer  (opus)   - Analyze requirements"
echo "  - coder     (sonnet) - Implement code"
echo "  - tester    (sonnet) - Write tests"
echo "  - reviewer  (opus)   - Code review"
echo "  - security  (opus)   - Security audit"
echo "  - devops    (sonnet) - CI/CD"
echo "  - documenter (haiku) - Documentation"
echo ""
echo "See .claude/AGENTS.md for detailed usage instructions."
