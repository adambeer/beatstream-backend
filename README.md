# Beat-Stream App PHP Backend #

## Description ##
This is the backend for the Beat-Stream music streaming app.

## Requirements ##
* [PHP 5.4.0 or higher](http://www.php.net/)
* MySQL
* Optional: Google API key for accessing YouTube's search API
* Optional: Last.fm API key for accessing artist/song database used for searching

## Developer Documentation ##
http://adambeer.ca/beatstream

**ATTENTION!** The steps that are labled as "optional" above and below are optional because the features will work out-of-the-box but you may want to use your own keys for both YouTube and the Last.fm search service. The keys provided with the backend are my own keys and if everyone were to use them, errors would start to pop up saying too many requests are being sent. Using your own key will ensure that only your activity is counted towards these services and you wont run into problems.

## Installation ##
* First get web hosting. Set it up on your computer (advanced), use <a href="http://ambientwave.com" target="_blank">my company</a>, or use your preferred web hosting provider.</li>
* Download the <a href="https://github.com/adambeer/beatstream-backend/" target="_blank">App backend zip file</a> from GitHub.</li>
* Upload the ZIP file to a separate folder on your web hosting and unzip the ZIP file</li>
* Optional: Create a new <a href="https://console.developers.google.com/apis/library" target="_blank">Google API Project</a> as well as an API key for accessing YouTube</li>
  * In the top left beside Google APIs, click the dropdown and select New Project</li>
  * Enter a name and push Create. With your new project selected, click "Credentials" on the left</li>
  * Push the Create Credentials dropdown and select API Key. Copy the value from the input and then push Close.</li>
  * Open the server_settings.php file with any text editor and paste the API key value in the place of the current value of $youtube_API_key</li>
  * Save the file and make sure it is re-uploaded/pushed to the web server</li>
* Optional: Get a <a href="http://www.last.fm/api" target="_blank">Last.fm API Account</a> for searching for artists/songs in the app.</li>
  * Click Get an API Account in the top-ish right</li>
  * Follow the steps to create the account</li>
  * Your API key will be displayed on the page once your account is created</li>
  * Open the server_settings.php file with any text editor and paste the API key value in the place of the current value of $lastfm_API_key</li>
  * Save the file and make sure it is re-uploaded/pushed to the web server</li>
* Create a new MySQL database, and copy the contents of the .sql file included in the ZIP and execute the code through something like phpMyAdmin (if you dont know how to do this, Google it)</li>
* Open the server_settings.php file with any text editor and update the database settings to your newly created database. Save the file and make sure it is re-uploaded/pushed to the web server.</li>
* Thats it! You'll want to open your app now and when prompted, type out or paste the url to the app backend files. If you created a "musicapp" folder on your website, the url will be http://yourwebsite.com/musicapp.</li>
