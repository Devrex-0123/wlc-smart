# Supplier Management System Documentation

## Overview
A complete supplier management system has been implemented for the super admin to manage suppliers with CRUD operations (Create, Read, Update, Delete). The system includes a beautiful, responsive UI with advanced features.

## File Structure

### Frontend Files
- **Page**: `public/pages/supplier_management.php`
  - Main page for supplier management
  - Responsive grid and table views
  - Integrated with existing sidebar navigation
  - Beautiful modals for adding/editing suppliers
  - Delete confirmation modal

- **Stylesheet**: `public/assets/css/supplier_management.css`
  - Comprehensive styling with animations
  - Beautiful card design for supplier display
  - Enhanced modal styling with gradient backgrounds
  - Image upload preview styling
  - Fully responsive design
  - Toast notification styles

- **JavaScript**: `public/assets/js/supplier_management.js`
  - Full CRUD operations
  - Real-time search functionality
  - Image preview before upload
  - Toast notifications for user feedback
  - Modal management
  - Data validation
  - Smooth animations and transitions

### Backend Files
- **API Endpoint**: `app/api/supplier_management.php`
  - Handles all supplier operations: list, get, add, edit, delete
  - Image upload management with automatic file organization
  - Activity logging for audit trail
  - Error handling and validation
  - Secure file operations

### Database
- **Migration**: `app/migrations/20260310_create_suppliers_table.sql`
  - Creates the suppliers table with all necessary fields
  - Includes proper indexing and constraints

## Database Table Structure

```sql
suppliers (
    supplier_id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(100) NOT NULL,
    contact_person VARCHAR(100),
    phone_number VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    city VARCHAR(50),
    country VARCHAR(50),
    postal_code VARCHAR(20),
    date_added TIMESTAMP DEFAULT current_timestamp(),
    status VARCHAR(20) DEFAULT 'Active',
    supplier_image VARCHAR(100)
)
```

## Features Implemented

### 1. **Supplier Card Grid View** 
   - Modern card design with image preview
   - Status badges (Active/Inactive)
   - Contact information display
   - Quick action buttons (Edit/Delete)
   - Responsive grid layout
   - Loading skeleton animations

### 2. **Add Supplier Modal**
   - Beautiful form with organized sections
   - Image upload with preview
   - Form validation
   - All fields properly labeled
   - Status selection dropdown
   - Multiple form sections for better UX

### 3. **Edit Supplier Modal**
   - Pre-populated form fields
   - Current image preview
   - Image replacement capability
   - Same validation as add functionality

### 4. **Delete Confirmation Modal**
   - Elegant delete confirmation
   - Shows supplier name to be deleted
   - Warning message
   - Confirmation and cancel buttons

### 5. **Search Functionality**
   - Real-time supplier search
   - Searches across multiple fields:
     - Supplier name
     - Contact person
     - Email
     - Phone number
     - City
   - Instant results filtering

### 6. **Image Management**
   - Upload and store supplier images
   - Automatic image organization in `uploads/suppliers/`
   - Image preview in cards and forms
   - Old image cleanup on update
   - Support for multiple image formats

### 7. **Activity Logging**
   - All actions logged to user_activity table
   - Tracks: Add, Edit, Delete operations
   - Includes user ID, timestamp, and details
   - Useful for audit trails

### 8. **Toast Notifications**
   - Success notifications (green)
   - Error notifications (red)
   - Auto-dismiss after 4 seconds
   - Smooth animations

### 9. **Responsive Design**
   - Mobile-friendly layout
   - Adaptive grid system
   - Touch-friendly buttons
   - Optimized modals for all screen sizes

## Theme & Design Consistency

✅ **Maintained Existing Theme:**
- Same color scheme (Green gradients: #16a34a to #15803d)
- Consistent typography (Inter font)
- Matching sidebar styling
- Similar animations and transitions
- Comparable button styles
- Same shadow and border-radius patterns

✅ **Enhanced UI Elements:**
- Improved card design with better spacing
- More appealing modals with gradient overlays
- Better form organization with form-rows
- Smooth animations throughout
- Professional delete confirmation modal
- Beautiful image preview container

## Navigation Integration

The supplier management link has been added to the sidebar of all main pages:
- Dashboard
- Account Management
- Facility Management  
- Item Management
- Inventory Management
- Logged History

Each page includes the link with the truck icon (`fas fa-truck`) for easy identification.

## API Endpoints

### POST: `/app/api/supplier_management.php`

**Actions:**

1. **list_suppliers**
   - Returns all suppliers sorted by date_added DESC
   - No parameters required

2. **get_supplier**
   - Returns single supplier by ID
   - Parameters: `supplier_id`

3. **add_supplier**
   - Creates new supplier
   - Parameters: `supplier_name` (required), `contact_person`, `phone_number`, `email`, `address`, `city`, `country`, `postal_code`, `status`
   - Optional: `supplier_image` (file upload)

4. **edit_supplier**
   - Updates existing supplier
   - Parameters: Same as add_supplier + `supplier_id` (required)
   - Optional: `supplier_image` (for image replacement)

5. **delete_supplier**
   - Deletes supplier and associated image
   - Parameters: `supplier_id` (required)

## Usage Instructions

### For Super Admin:

1. **Access Page**: Navigate to "Supplier Management" from the sidebar menu
2. **Add Supplier**: Click "+ Add Supplier" button
3. **Fill Form**: Enter supplier details
4. **Upload Image** (Optional): Click "Choose Image" to upload supplier logo/image
5. **Save**: Click "Save Supplier"
6. **Edit Supplier**: Click Edit button on any supplier card
7. **Delete Supplier**: Click Delete button, confirm in modal
8. **Search**: Use search box to filter suppliers by name, contact, email, phone, or city

### Form Validation:
- Supplier name is required
- Duplicate supplier names are prevented
- Email format validation
- Phone number format validation

## File Upload Details

- **Upload Directory**: `public/uploads/suppliers/`
- **File Organization**: Timestamp + random hash + filename
- **Allowed Types**: All image formats (jpg, png, gif, webp, etc.)
- **Automatic Directory Creation**: Backend creates directory if not exists
- **Image Cleanup**: Old images deleted when supplier updated or deleted

## Security Features

✅ **Session Validation**: Requires active user session
✅ **SQL Injection Prevention**: Uses prepared statements (PDO)
✅ **XSS Prevention**: HTML escaping on all output
✅ **File Upload Security**: Validates and organizes uploads
✅ **Activity Logging**: Tracks all modifications

## Browser Compatibility

- Chrome/Edge (v90+)
- Firefox (v88+)
- Safari (v14+)
- Mobile browsers (iOS Safari, Chrome Mobile)

## Performance

- Lazy loading of supplier images
- Skeleton loading animations
- Efficient search with real-time filtering
- Optimized CSS with minimal repaints
- Smooth animations using CSS transforms

## Future Enhancement Opportunities

1. Bulk import (CSV/Excel)
2. Export supplier list
3. Supplier categories/tags
4. Contact history/notes
5. Integration with purchase orders
6. Supplier performance ratings
7. Advanced filtering options
8. Supplier approval workflow

## Troubleshooting

**Issue**: Upload directory permissions error
- **Solution**: Ensure `public/uploads/` directory exists and is writable (755 permissions)

**Issue**: Images not displaying
- **Solution**: Check file paths and ensure uploads directory is accessible

**Issue**: Modal not opening
- **Solution**: Clear browser cache and reload page

**Issue**: Search not working
- **Solution**: Ensure JavaScript is enabled and `supplier_management.js` is loaded

## Support

For issues or questions, check:
1. Browser console for JavaScript errors
2. PHP error logs for backend issues
3. Database connection status
4. File system permissions for uploads

---

**Created**: March 10, 2026
**System**: CWIRMS - Central Warehouse Inventory & Resource Management System
