@echo off
rem Batch builder: khoi dong claude-proxy (cong 8791) khi onlogon.
rem Autostart on Windows login only. Latin-only text to avoid codepage issues.
set "NODE_EXE=C:\Program Files\nodejs\node.exe"
if not exist "%NODE_EXE%" set "NODE_EXE=node"

netstat -ano | findstr /R /C:"LISTENING.*:8791" >nul 2>&1
if not errorlevel 1 (
  exit /b 0
)

start "claude-proxy" /MIN "%NODE_EXE%" "%~dp0..\scratch\claude-proxy.mjs"