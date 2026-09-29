Set sh = CreateObject("WScript.Shell")
sh.CurrentDirectory = "C:\xampp\apache"
sh.Run """" & "C:\xampp\apache\bin\httpd.exe" & """ -d ""C:\xampp\apache""", 0, False
