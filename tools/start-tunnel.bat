@echo off
REM Double-click to start the tunnel and connect Meta to it. Keep the window open while testing.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0start-tunnel.ps1"
pause
