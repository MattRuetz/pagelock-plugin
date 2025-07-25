# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

No build process is required for this plugin. It's a pure PHP/CSS/JS WordPress plugin.

**Testing:** Manual testing only - no automated test framework configured.

## Architecture Overview

This is a WordPress plugin called "Pagelock" that provides password protection for pages with extensive customization options.

### Core Architecture

**Main Plugin File:** `pagelock.php` - Handles plugin initialization, activation, and deactivation hooks.

**Core Classes (in `includes/`):**
- `PageLock_Main` - Central controller, manages hooks and coordinates between components
- `PageLock_Admin` - Admin interface for creating/managing locks and customization settings
- `PageLock_Frontend` - Handles password protection logic and displays password forms
- `PageLock_Database` - Database operations for locks and settings

### Password Protection System

**Database Schema:** Custom table `wp_pagelock_locks` stores lock configurations:
- Each lock protects multiple pages with a single password
- Passwords are hashed using WordPress `wp_hash_password()`
- Lock settings include page IDs, password, custom styling options

**Authentication Flow:**
1. Frontend intercepts page requests via `template_redirect` hook
2. Checks if page requires protection by querying locks table
3. If protected and user not authenticated, displays password form
4. AJAX password verification with nonce security
5. Session-based authentication tracking (`$_SESSION['pagelock_authenticated_locks']`)

**Security Measures:**
- WordPress nonce verification on all forms
- User capability checks (`manage_options`) for admin functions
- Prepared SQL statements prevent injection
- Session-based auth (no sensitive data in cookies)

### Customization System

**Admin Interface:** Tabbed interface with:
- Lock management (create/edit/delete)
- Global styling settings (colors, fonts, layouts)
- Advanced options (mobile responsiveness, animations)

**Styling Options:**
- Custom icon uploads via WordPress media library
- Color schemes for all UI elements
- Background types: solid, gradient, image with overlay/blur effects
- Border radius controls and field styling (default vs minimal)
- Mobile-responsive design with device-specific optimizations

### File Structure

**Assets:**
- `assets/css/` - Separate stylesheets for admin and frontend
- `assets/js/` - JavaScript for admin interface and AJAX functionality

**Key Frontend Files:**
- Password form template rendered by `PageLock_Frontend::display_password_form()`
- AJAX handlers in `PageLock_Frontend` for password verification
- Dynamic CSS generation based on lock settings

## Important Implementation Details

**WordPress Integration:**
- Uses WordPress hooks system for initialization and request handling
- Integrates with WordPress media uploader for icon management
- Follows WordPress coding standards and security practices

**Database Operations:**
- Custom table created on plugin activation
- All queries use WordPress `$wpdb` with prepared statements
- Settings stored in WordPress options table

**Frontend Rendering:**
- Password forms are dynamically generated with inline CSS
- Uses WordPress jQuery for AJAX functionality
- Responsive design with mobile-first approach

## Cache Integration & Compatibility

**Critical for Authentication:** Password-protected pages are automatically excluded from caching to prevent the password form from being cached and served after successful authentication.

**Supported Cache Plugins:**
- WP Rocket: URI exclusions and query string handling
- W3 Total Cache: Page-level cache disabling
- WP Super Cache: Cache control variables
- WP Fastest Cache: Cache exclusion filters
- LiteSpeed Cache: Cacheable content filtering
- Autoptimize: Optimization exclusions
- Breeze (Cloudways): URL exclusions
- Cloudflare: Cache purging integration

**Cache Prevention Methods:**
- Standard HTTP no-cache headers (`Cache-Control`, `Pragma`, `Expires`)
- WordPress cache constants (`DONOTCACHEPAGE`, `DONOTCACHEOBJECT`, `DONOTCACHEDB`)
- Plugin-specific exclusion filters
- Post-authentication cache clearing for immediate content access

## Common Issues to Check

When debugging password authentication problems:
1. **Cache Issues:** Most authentication failures are caused by caching plugins serving cached password forms
2. Session handling - ensure sessions are properly started early in WordPress lifecycle
3. Password hashing consistency between storage and verification
4. Nonce verification not blocking legitimate requests
5. Database table existence and proper permissions
6. JavaScript/AJAX functionality across different browsers
7. Theme compatibility with custom CSS and form rendering
8. Cache plugin compatibility - check if protected pages are properly excluded