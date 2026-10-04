#!/bin/bash
# Fix: Remove router to fix blank page after login

echo "🧹 Removing router to fix blank page issue..."

cd public/assets/js/modules
rm -f router.js

cd ../..

# Remove from manifest
sed -i "s/'router', //" public/assets/asset_manifest.php

# Remove from shell.js - find and remove the router init block
sed -i '/Khởi tạo URL router/,/window.TNTT.router.init();/d' public/assets/js/modules/shell.js

echo "✅ Done! Router removed."
echo ""
echo "Commit changes:"
echo "  git add -A && git commit -m 'fix: remove router' && git push"
