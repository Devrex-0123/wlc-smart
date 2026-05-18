# Inventory Management - Quick Reference Guide

## File Structure

```
CWIRMS/
├── app/
│   └── api/
│       └── inventory_management.php (Added 3 new endpoints)
├── public/
│   ├── assets/
│   │   ├── css/
│   │   │   └── inventory_management.css (Added breadcrumb & badge styles)
│   │   └── js/
│   │       ├── inventory_hierarchy.js (NEW - Hierarchical navigation)
│   │       └── inventory_management.js (Modified - Integration with hierarchy)
│   └── pages/
│       └── inventory_management.php (Modified - 3-view structure)
└── IMPLEMENTATION_NOTES.md (Detailed documentation)
```

## Quick Start for Developers

### Understanding the Architecture

The new system works in 3 layers:

1. **Backend (API):** `app/api/inventory_management.php`
   - `get_departments` - Get all departments with statistics
   - `get_facilities_by_department` - Get rooms/labs in a department
   - `get_inventory_by_facility` - Get items in a facility

2. **Frontend (Hierarchy):** `public/assets/js/inventory_hierarchy.js`
   - Handles navigation between views
   - Manages breadcrumb updates
   - Loads data and renders tables

3. **Frontend (Inventory):** `public/assets/js/inventory_management.js`
   - Handles modals (Add/Edit/Delete)
   - Manages form submissions
   - Coordinates with hierarchy for context

### Navigation Functions

```javascript
// Start from inventory_management.js
window.inventoryHierarchy.viewFacilityInventory(facilityId, facilityName)
// Shows inventory items for a facility

window.inventoryHierarchy.showDepartmentsView()
// Returns to departments list

window.inventoryHierarchy.getCurrentFacilityId()
// Gets the currently selected facility ID
```

### Common Customizations

#### Adding a Filter to Departments View
Edit `inventory_hierarchy.js`, function `loadDepartments()`:
```javascript
// Add filter in the table rendering section
if (dept.lab_count > 0 || dept.room_count > 0) {
    // Render only departments with items
}
```

#### Changing Breadcrumb Style
Edit `inventory_management.css`:
```css
.breadcrumb-nav {
    /* Modify colors, spacing, or layout */
}
```

#### Modifying Statistics Display
Edit `inventory_hierarchy.js`, function `loadDepartments()`:
```javascript
// Change which statistics are displayed
// Current: lab_count, room_count, total_inventory
// Add more from API response if needed
```

### Debugging Tips

1. **Check console for errors:**
   - Open Developer Tools (F12)
   - Go to Console tab
   - Look for any red error messages

2. **Verify API responses:**
   - Open Network tab in Dev Tools
   - Click on a department
   - Look for `inventory_management.php` request
   - Check the Response tab

3. **Test state transitions:**
   - Add `console.log()` in `inventory_hierarchy.js`
   - Check breadcrumb updates
   - Verify `currentDepartmentId` and `currentFacilityId`

### Common Issues & Solutions

| Issue | Solution |
|-------|----------|
| Departments not loading | Check if `facilities` table exists in database |
| No inventory items shown | Verify `inventory` table has records with proper `facility_id` |
| Breadcrumb not updating | Check `inventory_hierarchy.js` for event listener issues |
| Add Inventory shows all facilities | Facility pre-selection might be overridden by user selection |
| Styles not applying | Ensure `inventory_management.css` is loaded after dashboard.css |

## API Response Format

### get_departments
```json
{
  "success": true,
  "departments": [
    {
      "department_id": "1",
      "department_name": "COAB",
      "lab_count": "4",
      "room_count": "3",
      "total_inventory": "20"
    }
  ]
}
```

### get_facilities_by_department
```json
{
  "success": true,
  "facilities": [
    {
      "department_id": "1",
      "department_name": "COAB",
      "laboratory": "Lab 1",
      "room": null,
      "type": "Laboratory",
      "total_inventory": "6"
    }
  ]
}
```

### get_inventory_by_facility
```json
{
  "success": true,
  "inventory": [
    {
      "inventory_id": "1",
      "name": "System Unit",
      "item_code": "IT-001",
      "quantity": "5",
      "facility_id": "1",
      "condition_status": "Good",
      "status": "Available",
      ...
    }
  ]
}
```

## Performance Considerations

1. **Database Queries:** Using subqueries for aggregation instead of GROUP BY (better MySQL compatibility)
2. **Event Listeners:** Re-attached on dynamic table loads via custom events
3. **View Toggling:** Using `display: none/block` instead of DOM manipulation
4. **Breadcrumb:** Updated by modifying text content and visibility

## Browser Compatibility

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

Uses:
- Fetch API (not XMLHttpRequest)
- ES6 async/await
- CSS Flexbox
- CSS Grid

## Testing Checklist for New Features

When adding new features to this module:

- [ ] Department statistics calculate correctly
- [ ] Breadcrumb navigation works at all levels
- [ ] Back button in breadcrumb shows previous view
- [ ] Add/Edit modal appears with correct facility pre-selected
- [ ] Inventory saves and reloads current facility
- [ ] Delete operation reloads current facility
- [ ] Modals still function for all operations
- [ ] Style is consistent with existing theme
- [ ] No console errors

## Future Enhancement Ideas

1. **Search & Filter**
   - Add department search at level 1
   - Add facility search at level 2
   - Add inventory search at level 3

2. **Batch Operations**
   - Select multiple items
   - Bulk status update
   - Bulk move between facilities

3. **Reporting**
   - Export to PDF/Excel
   - Generate inventory reports
   - Department inventory summaries

4. **Advanced Features**
   - Inventory movement tracking
   - Deprecation management
   - Maintenance scheduling

## References

- Main implementation: See `IMPLEMENTATION_NOTES.md`
- Theme colors: Check existing `dashboard.css`
- Database structure: See `cwirms.sql`
