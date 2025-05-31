# Pagelock WordPress Plugin

A WordPress plugin that allows administrators to password-protect specific pages with custom password locks, inspired by organic design elements and modern UI patterns.

## Features

- 🔒 **Custom Page Locks**: Create multiple password locks with unique passwords
- 📄 **Selective Protection**: Choose exactly which pages to protect with each lock
- 🎨 **Beautiful Design**: Password form inspired by nature-themed designs with organic shapes and modern aesthetics
- 🔧 **Easy Admin Interface**: Simple WordPress admin interface for managing locks
- 💫 **Enhanced UX**: Password strength indicator, page search, and improved form handling
- 🔐 **Secure**: Uses WordPress password hashing and nonce verification
- 📱 **Responsive**: Works perfectly on desktop and mobile devices
- 🌐 **Session-based**: Users stay authenticated during their session

## Installation

1. **Upload the Plugin**:

   - Download or clone this repository
   - Upload the `pagelock` folder to your `/wp-content/plugins/` directory
   - Or upload the zip file through WordPress admin

2. **Activate the Plugin**:

   - Go to WordPress Admin → Plugins
   - Find "Pagelock" and click "Activate"

3. **Database Setup**:
   - The plugin automatically creates necessary database tables on activation

## Usage

### Creating a Page Lock

1. **Navigate to Pagelock**:

   - Go to WordPress Admin → Pagelock

2. **Add New Lock**:
   - Click "Add New Lock"
   - Enter a descriptive name for your lock
   - Set a strong password
   - Select the pages you want to protect
   - Click "Create Lock"

### Managing Existing Locks

- **View All Locks**: Go to WordPress Admin → Pagelock
- **Edit a Lock**: Click "Edit" next to any lock in the list
- **Delete a Lock**: Click "Delete" and confirm (this will remove protection from all associated pages)

### Frontend Experience

When a visitor tries to access a protected page:

1. **Password Form**: A beautiful, nature-inspired password form appears
2. **Enter Password**: User enters the password for that specific lock
3. **Session Access**: Once authenticated, user can access all pages protected by that lock during their session
4. **Responsive Design**: Form works seamlessly on all devices

## Design Features

The password form includes:

- **Organic Background**: Subtle animated shapes inspired by nature
- **Modern Typography**: Clean, readable fonts
- **Color Scheme**: Earth tones with green/orange accents
- **Interactive Elements**: Hover effects and smooth transitions
- **Accessibility**: Proper focus states and keyboard navigation
- **Loading States**: Visual feedback during password verification

## Technical Details

### File Structure

```
pagelock/
├── pagelock.php                    # Main plugin file
├── includes/
│   ├── class-pagelock.php          # Main plugin class
│   ├── class-pagelock-admin.php    # Admin interface
│   ├── class-pagelock-frontend.php # Frontend password form
│   └── class-pagelock-database.php # Database operations
├── assets/
│   ├── css/
│   │   ├── admin.css               # Admin styling
│   │   └── frontend.css            # Frontend utilities
│   └── js/
│       ├── admin.js                # Admin functionality
│       └── frontend.js             # Frontend utilities
└── README.md
```

### Database Schema

The plugin creates one table: `wp_pagelock_locks`

| Column     | Type         | Description                  |
| ---------- | ------------ | ---------------------------- |
| id         | int(11)      | Primary key                  |
| name       | varchar(255) | Lock name                    |
| password   | varchar(255) | Hashed password              |
| pages      | text         | Serialized array of page IDs |
| created_at | datetime     | Creation timestamp           |
| updated_at | datetime     | Last update timestamp        |

### Security Features

- **Password Hashing**: Uses WordPress `wp_hash_password()` function
- **Nonce Verification**: All forms use WordPress nonces
- **Session Management**: Secure session-based authentication
- **Input Sanitization**: All inputs are properly sanitized
- **Permission Checks**: Admin functions require `manage_options` capability

## Admin Features

### Enhanced Form Handling

- **Password Strength Indicator**: Real-time password strength feedback
- **Page Search**: Search through pages when selecting which to protect
- **Select All/Deselect All**: Bulk selection for pages
- **Form Validation**: Client-side validation with helpful error messages

### Improved UX

- **Custom Delete Confirmation**: Beautiful modal instead of browser alert
- **Loading States**: Visual feedback during operations
- **Success/Error Messages**: Clear feedback for all actions
- **Responsive Design**: Works well on all screen sizes

## Customization

### Styling the Password Form

The password form uses inline CSS for better performance, but you can override styles by adding CSS to your theme:

```css
.pagelock-container {
  /* Your custom styles */
}

.pagelock-button {
  /* Customize the button */
}
```

### Modifying Colors

The main colors used in the design:

- Background: `#F8E7CE` (Grassroot White)
- Green accent: `#A5AB52` (Solidarity Canopy Green) and `#566246` (Forest Floor Green)
- Orange accent: `#ED9A25` (Youth Ignition Orange)
- Text: `#6C0E23` (Rally On Red) and `#46351D` (Grounded Boot Brown)
- Teal accent: `#007C77` (Community Time Teal)
- Pink accent: `#D45874` (Radical Bloom Pink)

### Adding Custom Hooks

The plugin provides several hooks for customization:

```php
// Modify password form content
add_filter('pagelock_password_form_content', 'your_custom_function');

// Customize admin page content
add_action('pagelock_admin_page_header', 'your_header_function');
```

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

## Browser Support

- Chrome 70+
- Firefox 65+
- Safari 12+
- Edge 79+
- Mobile browsers with ES6 support

## Changelog

### Version 1.0.0

- Initial release
- Custom page locks with password protection
- Beautiful nature-inspired password form
- Enhanced admin interface
- Session-based authentication
- Mobile-responsive design

## Support

For support, feature requests, or bug reports, please create an issue in the repository or contact the plugin developer.

## License

This plugin is licensed under the GPL v2 or later.

---

**Pagelock** - Protecting your content with style and security.
