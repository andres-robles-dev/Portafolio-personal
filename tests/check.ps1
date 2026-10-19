$ErrorActionPreference = 'Stop'
Push-Location (Split-Path $PSScriptRoot -Parent)
try {
    Get-ChildItem -Path api,scripts,tests -Recurse -File -Filter *.php | ForEach-Object {
        php -l $_.FullName
        if ($LASTEXITCODE -ne 0) { throw "Sintaxis PHP invalida: $($_.Name)" }
    }
    php -l router.php
    if ($LASTEXITCODE -ne 0) { throw 'Router PHP invalido.' }
    Get-ChildItem -Path assets/js,scripts,tests -Recurse -File | Where-Object { $_.Extension -in '.js','.mjs' } | ForEach-Object {
        node --check $_.FullName
        if ($LASTEXITCODE -ne 0) { throw "Sintaxis JS invalida: $($_.Name)" }
    }
    node --check local-server.mjs
    if ($LASTEXITCODE -ne 0) { throw 'Servidor Node invalido.' }
    node --test tests/*.test.mjs
    if ($LASTEXITCODE -ne 0) { throw 'Fallaron pruebas Node/HTTP.' }
    php tests/security.php
    if ($LASTEXITCODE -ne 0) { throw 'Fallaron pruebas PHP.' }
    node scripts/block-hardcode.mjs --history
    if ($LASTEXITCODE -ne 0) { throw 'Revisar secretos/hardcodes.' }
    node scripts/release-version.mjs verify
    if ($LASTEXITCODE -ne 0) { throw 'Version de recursos inconsistente.' }
} finally {
    Pop-Location
}
