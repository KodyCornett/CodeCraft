# CodeCraft

A text-based hacking game where the desktop is the world and the terminal is the only way to act.

## Architecture

```
[Browser UI]
     ↓
[Laravel Backend]  ←  web/
     ↓ API / WebSocket
[Kotlin Engine]    ←  engine/
```

- **Laravel** handles UI rendering, user accounts, saves, missions, and content
- **Kotlin** handles command parsing, game simulation, and deterministic outcomes

## Project Structure

```
CodeCraft/
├── web/           # Laravel application (frontend + API)
├── engine/        # Kotlin/Ktor game engine (Phase 2)
└── README.md
```

## Current Phase

**Phase 0 - Foundations & Architecture** (Complete)

- [x] Git branching structure
- [x] Laravel project scaffold
- [x] GameEngineInterface abstraction
- [x] MockGameEngine for UI development
- [x] Kotlin project structure (placeholder)

**Next: Phase 1 - Diegetic Desktop UI**

## Development

### Requirements

- PHP 8.2+
- Composer
- PostgreSQL (or SQLite for quick local dev)
- Node.js 18+ (for Vite/frontend assets)
- JDK 21+ (Phase 2, for Kotlin engine)

### Setup

```bash
# Laravel
cd web
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run dev

# In another terminal
php artisan serve
```

### Git Workflow

- `main` - production-ready code
- `develop` - integration branch
- `feature/*` - feature branches

## Design Principles

1. **Diegetic UI** - No game menus, no HUD. Everything is an "app"
2. **Commands are discovered** - Players find commands through files, logs, and contacts
3. **Files as knowledge** - Answers are hidden in the UI, not the terminal
4. **Learning the UI is part of the game** - The computer feels real before it does anything interesting
