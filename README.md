# Custom Mortgage Calculator

A WordPress plugin that provides a comprehensive mortgage calculator with real-time UVA (Unidad de Valor Adquisitivo) rates integration from Argentina's BCRA (Banco Central de la República Argentina).

## Features

- **Multi-step mortgage calculation form** with intuitive step-by-step interface
- **Real-time UVA rates** fetched from BCRA API
- **Market context integration** providing current economic indicators
- **Email notifications** for form submissions
- **Database storage** of calculation results
- **Responsive design** optimized for mobile and desktop
- **Spanish localization** with translation support
- **AJAX-powered** for seamless user experience

## Installation

1. Upload the `custom-mortgage-calculator` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Use the shortcode `[mortgage_calculator]` to display the calculator on any page or post

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- MySQL 5.6 or higher

## Shortcode Usage

```
[mortgage_calculator]
```

## Features Details

### UVA Integration
- Automatically fetches daily UVA rates from BCRA
- Caches rates for performance optimization
- Falls back to manual rates if API is unavailable

### Calculation Types
- Traditional mortgage calculations
- UVA-adjusted mortgage calculations
- Down payment requirements
- Monthly payment estimations

### Email Notifications
- Sends calculation results to users
- Admin notifications for new submissions
- Customizable email templates

### Database Storage
- Stores all calculation submissions
- Tracks user interactions
- Enables reporting and analytics

## File Structure

```
custom-mortgage-calculator/
├── css/
│   └── mortgage-calculator.css      # Plugin styles
├── js/
│   └── mortgage-calculator.js       # Frontend JavaScript
├── includes/
│   ├── ajax-handlers.php           # AJAX request handlers
│   ├── bcra-api.php               # BCRA API integration
│   ├── calculations.php           # Mortgage calculation logic
│   ├── database.php               # Database operations
│   ├── email-notifications.php    # Email functionality
│   ├── market-context.php         # Market data integration
│   ├── templates.php              # HTML templates
│   └── uva-functions.php          # UVA-specific functions
├── languages/                      # Translation files
│   ├── custom-mortgage-calculator-es_ES.mo
│   ├── custom-mortgage-calculator-es_ES.po
│   └── custom-mortgage-calculator.pot
├── custom-mortgage-calculator.php  # Main plugin file
└── index.php                      # Security index
```

## Development

### AJAX Endpoints

The plugin provides several AJAX endpoints:
- `mc_calculate_mortgage` - Performs mortgage calculations
- `mc_get_uva_rate` - Retrieves current UVA rates
- `mc_save_calculation` - Saves calculation results

### Hooks and Filters

Available filters:
- `mc_calculation_results` - Modify calculation results
- `mc_email_subject` - Customize email subjects
- `mc_email_content` - Customize email content

### JavaScript Events

The calculator triggers custom events:
- `mc:step-changed` - When user navigates between steps
- `mc:calculation-complete` - When calculation finishes
- `mc:form-submitted` - When form is submitted

## Styling

The plugin uses custom CSS with responsive design. Main classes:
- `.mortgage-calculator-container` - Main container
- `.mc-step` - Individual form steps
- `.mc-progress-bar` - Progress indicator
- `.mc-results` - Results display section

## Localization

The plugin is translation-ready with full Spanish support. To add new translations:
1. Use the POT file as a template
2. Create new PO/MO files for your language
3. Place them in the `languages` directory

## Support

For support, please contact the development team or submit issues through the appropriate channels.

## License

This plugin is proprietary software developed for Tasalink.

## Credits

Developed by Helbetica (helbetica@outlook.com)