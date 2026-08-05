# External FPP Web Access

> **Add a second, password-protected web address for your FPP.** Visitors log in with a username and password before they reach the FPP dashboard.

---

## What it does

FPP's normal web UI (port `80`) is left exactly as it is. This plugin opens an **additional web address** that shows the *same* FPP interface, but only after a **login page**.

Use it when you want to:

- share access to your lights without handing out the main FPP address,
- add an extra layer of credentials in front of the dashboard,
- let guests or remote users in without exposing the regular UI.

No extra software is needed — it uses the web server that is already built into FPP.

---

## Before you start

- FPP 8 or newer
- FPP's web UI working on its normal port (default `80`)

---

## Installation

### Recommended: Plugin Manager

1. In FPP's UI, go to **Content Setup → Plugin Manager**.
2. Paste this address and click **Get Plugin Info**:

   ```
   https://raw.githubusercontent.com/jessica12ryan/fpp-ExternalFPP/main/pluginInfo.json
   ```

3. Click **Install** next to "External FPP Web Access".

### Manual install

For advanced users who prefer the command line, see [CONTRIBUTING.md](CONTRIBUTING.md).

---

## First-time setup

1. In FPP, go to **Content Setup → External FPP**.
2. Open the **Users** tab and add at least one user (a username and password).
3. Open the **Config** tab and pick an **Listen Port** (the default `8080` is usually fine).
4. Click **Save & Apply**.
5. Click the button to **enable** external access.

Now browse to:

```
http://<your-fpp-ip>:8080/
```

You'll see a login page. Enter the username and password you created to reach the FPP dashboard.

> **Note:** You need at least one user to turn this on, and you can't delete the last user while it's enabled.

---

## Customizing the login page

The login page is ordinary HTML you can edit in a text box:

1. Open the **Pages** tab in the plugin.
2. Edit the **Login Page** or the **Change Password Page**.
3. Click **Save** — your change is applied straight away.

The **Pages** tab tells you which bits are required for the page to work and warns you if they're missing.

---

## Changing a user's password

- To change **someone else's** password, use **Change Password** in the **Users** tab.
- To force a user to set a new password next time they log in, tick **must change password at next login** in the Users tab. They'll land on the password page and be held there until they do.

---

## Signing out

Visit:

```
http://<your-fpp-ip>:8080/logout
```

This removes your login and returns you to the login page.

---

## Common questions

### "I can't reach the external port"

- Check the **Status** tab — the "Apache vhost enabled" and "port listening" indicators should both be green.
- Make sure the port isn't already used by something else on your network.

### "I still get asked for a password after logging in"

- Turn off FPP's own **built-in UI password** (Status/Control → FPP Settings → UI tab), because this plugin already provides one.
- Or, when prompted for a second password, enter FPP's admin credentials (username `admin` and FPP's UI password).

### "Is my password safe?"

The additional port uses plain HTTP, and login details are carried in the session cookie. Don't share that port with the public internet — put it behind a VPN or an encrypted reverse proxy for remote access. See [SECURITY.md](SECURITY.md) and [CONTRIBUTING.md](CONTRIBUTING.md) for details.

---

## License

Copyright © 2026 jessica12ryan. Licensed under the MIT License.

FPP is © Falcon Christmas. This plugin is an independent integration and is not affiliated with or endorsed by the Falcon Christmas project.