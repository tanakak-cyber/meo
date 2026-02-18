Set WshShell = CreateObject("WScript.Shell")
WshShell.Run "C:\laragon\bin\php\php-8.3.26-Win32-vs16-x64\php.exe C:\laragon\www\meo\artisan schedule:run", 0, False
Set WshShell = Nothing













