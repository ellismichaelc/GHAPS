GHAPS - GitHub Auto-Publish Server
=====

Why a standalone server script when you can just setup a web hook?

Simple. GHAPS can handle multiple virtual hosts. Chances are, on your web server you have different directories and different users for your separate websites. A standard webhook that hits a script in your public folder on your website will only have permission as the current user, who typically has permission only to that specific vhost's folder. The solution for me was to create a single user with access to all of those directories, and run GHAPS on a separate port as that user.

When run, GHAPS will duplicate itself, and then run itself within it's own container. This way if the script is updated, or needs to be killed for any reason it's always running as a 'service'. If the script dies for any reason, it will auto-restart itself.

GHAPS will listen on the specified port ($sock_port, default 8181) for incoming connections. When an incoming request is received, GHAPS will verify that it's within the list of specified hosts ($ip_list, prefilled with GitHub's IPs). Next, it will grab the 'payload' aka the data being sent by GitHub, sort through it and identify the repo ID associated with the pull request. The repo ID will be compared with your configuration ($repo_dirs), if the repo is within your configuration GHAPS will change directories to the folder specified and start the git pull process. 

If equipped ($cmd_fpull not empty), GHAPS will detect when a git pull fails due git refusing for one reason or the other and run a hard reset.

I've been using GHAPS on my production server for almost a year now, and it's been running stable, reliable, and securely the entire time. I push my updates to my repos, and GHAPS gets it running on my web server.

GHAPS was created out of necessity, and is maintained as a portfolio item / side project.



Setup / Configuration
=====
1. Create a user on your webserver with the appropriate permissions to access all your vhosts folders
2. In that users home directory, place the GHAPS script, and configure it appropriately
3. Place 'ghaps.sh' in /etc/init.d/ to have it start automatically at boot, and chmod 777
4. Go to your 'Webhooks & Services' settings page in GitHub, simply put your servers IP and port (default 8181)


Commands
=====
- If your IP is on the list of allowed hosts ($ip_list), you may send commands to GHAPS through your browser
- The format is as follows: http://your_server_hostname:8181/command
- Currently the only commands accepted are: 'restart', 'quit' or 'exit'
