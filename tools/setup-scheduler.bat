@echo off
REM Double-click to make the app's automatic jobs (reminders, automations, daily email) run by themselves.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0setup-scheduler.ps1"
pause
