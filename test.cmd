@echo off

echo Doing a build, too, 'coz I always forget that...
echo.
call build.cmd
if errorlevel 0 goto :start
goto :eof

:start
start php -S localhost:1111
