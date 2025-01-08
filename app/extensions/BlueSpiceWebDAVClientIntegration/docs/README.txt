=="Some files can harm your computer ..."-Prompt==
* http://blog.incworx.com/blog/who-is-the-best-chicago-sharepoint-firm/sharepoint-help-editing-a-document-within-sharepoint-2010
* <s>https://social.technet.microsoft.com/Forums/sharepoint/en-US/fc766c2a-11a8-4f28-9ca1-d7837d777c6c/some-files-can-harm-your-computer</s>
* <s>http://sharepoint.stackexchange.com/questions/1754/eliminating-the-some-files-can-harm-your-computer-warning-prompt</s>

<s>Try this:

Go to Windows explorer under "Tools" --> "Folder options...",
Select the "File types" option.
Highlight the word file type (.doc and .docx) and click on the "advanced" button.
The value corresponds to the first check box "Confirm open after download". If you remove this option, the files are opened without any prompt</s>

==CORE HACK==
To use the client integration on a MediaWiki < 1.24 you will need to replace the
Linker::makeMediaLinkFile method with the one in the file 'Linker.makeMediaLinkFile.snippet'