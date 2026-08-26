# Prefer diagnose-fix-iis.bat instead — it covers this same
# server-level FastCGI registration plus site-level handler
# mapping, module checks, self-elevation, and a live test, in
# one script. Kept here for reference / PowerShell-native use.

$php = 'C:\Users\HendiWibowo\.local\bin\php-cgi.exe'

# Beri akses baca+eksekusi ke identitas app pool IIS (wajib, karena PHP ada di folder profil user)
icacls 'C:\Users\HendiWibowo\.local\bin' /grant 'IIS_IUSRS:(OI)(CI)RX'

# Daftarkan php-cgi sebagai aplikasi FastCGI
& "$env:windir\System32\inetsrv\appcmd.exe" set config /section:system.webServer/fastCgi "/+[fullPath='$php']"

# Tambahkan handler mapping *.php di level server
& "$env:windir\System32\inetsrv\appcmd.exe" set config /section:system.webServer/handlers "/+[name='PHP_via_FastCGI',path='*.php',verb='GET,HEAD,POST',modules='FastCgiModule',scriptProcessor='$php',resourceType='Either']"

iisreset
