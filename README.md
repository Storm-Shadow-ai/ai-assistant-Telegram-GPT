# Storm-Shadow-AI

Storm-Shadow-AI is a lightweight AI assistant platform powered by the OpenAI API.
The project provides a chatbot engine, administration panel, usage limits, and proxy routing for secure API communication.

---

## Features

* OpenAI API integration
* Telegram / API bot support
* Admin panel
* Request limits and usage control
* Proxy support
* Logging system
* Modular architecture

---

## Requirements

* PHP 7.4+
* MySQL / MariaDB
* Web server (Nginx / Apache)

---

## Installation

Clone repository:

```
git clone https://github.com/YOUR_USERNAME/storm-shadow-ai.git
```

Go to project directory:

```
cd storm-shadow-ai
```

Copy configuration file:

```
cp config/config.example.php config/config.php
```

Edit configuration and insert your API keys.

---

## Security

Never commit:

* API keys
* proxy credentials
* database passwords

Use `.gitignore` for sensitive data.

---

## License

MIT License
