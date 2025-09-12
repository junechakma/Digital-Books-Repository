# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

This is a React + TypeScript + Vite application configured with TailwindCSS for styling. The project follows a minimal setup approach with modern tooling and strict TypeScript configuration.

**Tech Stack:**
- React 19.1.1 with TypeScript
- Vite 7.1.2 for build tooling and dev server
- TailwindCSS 4.1.13 for styling
- ESLint with TypeScript support for code quality

## Development Commands

```bash
# Start development server with hot reload
npm run dev

# Build for production (TypeScript compilation + Vite build)
npm run build

# Lint all TypeScript/JavaScript files
npm run lint

# Preview production build locally
npm run preview

# Install dependencies
npm install
```

### Testing

No test runner is currently configured in package.json. If you plan to add tests, the repository commonly pairs Vite with Vitest and React Testing Library. Suggested setup:

```bash
# Add dev dependencies
npm i -D vitest @vitest/ui @testing-library/react @testing-library/user-event @testing-library/jest-dom jsdom

# Example scripts to add to package.json
#   "test": "vitest",
#   "test:ui": "vitest --ui",
#   "test:watch": "vitest --watch",
#   "test:coverage": "vitest run --coverage"
```

Running tests (after setup):

```bash
# All tests
npm run test

# Watch mode
npm run test:watch

# Single test file
npx vitest run src/components/MyComponent.test.tsx

# Single test by name (pattern)
npx vitest run -t "renders MyComponent"
```

## Project Structure

```
src/
├── App.tsx          # Main application component
├── main.tsx         # React application entry point
├── index.css        # Global styles and CSS variables
└── vite-env.d.ts    # Vite TypeScript environment types

public/              # Static assets
├── logo.png
└── ugc-latest.png

Configuration files:
├── vite.config.ts          # Vite configuration with React and TailwindCSS plugins
├── eslint.config.js        # ESLint configuration with TypeScript rules
├── tsconfig.json           # TypeScript project references
├── tsconfig.app.json       # App-specific TypeScript config
└── tsconfig.node.json      # Node/build tools TypeScript config
```

## Code Architecture

High-level execution flow:
- index.html boots the app and loads /src/main.tsx via ESM.
- src/main.tsx initializes React 19 using createRoot and mounts <App/> under StrictMode.
- src/App.tsx is the top-level application component. As of now it is minimal and ready to grow into routes, layout shells, and feature modules.
- Styling is handled globally by TailwindCSS (via the Vite plugin) and src/index.css for project color variables.

**TypeScript Configuration:**
- Strict TypeScript settings enabled across the project
- Separate TypeScript configurations for app code (`tsconfig.app.json`) and build tools (`tsconfig.node.json`)
- ES2022 target for app code, ES2023 for build tools
- React JSX transform configured
- No global state library is present; component-local state is expected unless a state solution is introduced later.

**Styling:**
- TailwindCSS integrated via Vite plugin
- Global CSS variables defined in `src/index.css`:
  - Primary color: `#ca1d26`
  - Text color: `#2b2e33` 
  - Background: `#ffffff`

**Build System:**
- Vite handles both development and production builds
- Hot Module Replacement (HMR) enabled for fast development
- TypeScript compilation runs before Vite build in production

## Development Guidelines

**Component Development:**
- All React components should be written in TypeScript (`.tsx` files)
- Place components in the `src/` directory
- Use functional components with React hooks

**Styling:**
- Use TailwindCSS utility classes for styling
- Reference the defined color variables in `src/index.css` for brand consistency
- Global styles should be added to `src/index.css`

**Code Quality:**
- Run `npm run lint` before committing changes
- ESLint is configured with React hooks and React refresh plugins
- TypeScript strict mode is enforced with additional linting rules

**File Organization:**
- Keep the flat structure in `src/` for this minimal project
- Static assets go in the `public/` directory
- Configuration files remain in the project root

## Product Scope (provided context; not yet implemented in this repo)

The following user-facing and admin features are part of the intended product scope shared in conversation. Treat these as requirements/targets when implementing features; they are not present in the codebase yet.

Public Interface
- Homepage: Centered search bar; real-time suggestions with debounced input
- Book Display: Grid layout showing title, author, subject
- Cart System: Add/remove books with persistence
- Checkout: University email validation
- OTP Verification: Modal with 6-digit input
- Download: Secure PDF download links

Admin Dashboard
- Login: Secure admin authentication
- Overview: Stats and recent activity
- Book Management: CRUD for books
- File Upload: Drag-drop with progress
- Bulk Operations: Multi-select actions
- Search & Filter within admin
- Responsive on desktop/tablet

Styling & UI
- Tailwind-based design system, university-themed colors, responsive grid
- Interactive UX: hovers, loading states, transitions, toasts, modals
