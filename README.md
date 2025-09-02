<!-- Banner -->
<h1 align="center">🔐 CipherChat</h1>
<h3 align="center">Access secure conversations through encrypted links</h3>

<p align="center">
  <a href="http://cipherchat.byethost16.com/">
    <img src="https://img.shields.io/badge/Visit%20Live%20Site-36BCF7?style=for-the-badge&logo=google-chrome&logoColor=white" />
  </a>
  <a href="https://github.com/Amosgamer04/cipherchat">
    <img src="https://img.shields.io/badge/GitHub%20Repo-181717?style=for-the-badge&logo=github&logoColor=white" />
  </a>
</p>

---

## 📌 Overview  

**CipherChat** is a lightweight secure messaging app that allows you to **share conversations via encrypted links**.  
No account required — simply generate a secure link, share it, and start chatting privately.  

👉 **Live Demo:** [cipherchat.byethost16.com](http://cipherchat.byethost16.com/)  

---

## ✨ Features  

✅ End-to-End Encrypted Messaging  
✅ Temporary Links (auto-cleanup of expired chats)  
✅ Simple, Modern, and Lightweight UI  
✅ Built with **PHP + HTML + JavaScript**  
✅ No signup/login required  

---

## 🛠️ Tech Stack  

- **Frontend:** HTML5, CSS3, JavaScript  
- **Backend:** PHP  
- **Database:** MySQL  
- **Security:** Encrypted links with expiration cleanup  

---

## 🚀 How It Works  

1. Generate a secure chat link.  
2. Share the link with your friend.  
3. Start a private conversation.  
4. When the link expires → the chat is deleted automatically.  

---

## 📂 Project Structure  

```bash
cipherchat/
│── admin.html            # Admin access page
│── cleanup_expired.php   # Cleans up expired chats
│── config.php            # Database config
│── index.html            # Landing page
│── message_api.php       # Handles chat messages
│── save_link.php         # Saves generated chat links
│── secure.html           # Chat UI
│── secure.php            # Secure session handling
│── Screenshot.png        # Project screenshot
└── README.md             # Project documentation
