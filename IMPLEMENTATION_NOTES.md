# Inventory Management Hierarchical Navigation - Implementation Summary

## Overview
The Inventory Management module has been restructured to provide a hierarchical navigation flow:

1. **Departments View** - Shows all departments with statistics (Total Labs, Total Rooms, Total Inventory)
2. **Facilities View** - Shows rooms and labs within selected department
3. **Inventory View** - Shows inventory items within selected facility

---

## Files Modified/Created

### 1. **Backend - API Endpoints**
**File:** `app/api/inventory_management.php`

**New Endpoints Added:**

#### `get_departments`
- Returns all departments with statistics
- Counts: labs, rooms, and total inventory quantity
- Uses subqueries for compatibility with MySQL strict mode
- **Response Format:**
  ```json
  {
    "success": true,
    "departments": [
      {
        "department_id": 1,
        "department_name": "COAB",
        "lab_count": 4,
        "room_count": 3,
        "total_inventory": 20
      }
    ]
  }
  ```

#### `get_facilities_by_department`
- Returns all facilities (labs and rooms) within a department
- Parameters: `department_id`
- Includes inventory count for each facility
- **Response Format:**
  ```json
  {
    "success": true,
    "facilities": [
      {
        "department_id": 1,
        "department_name": "COAB",
        "laboratory": "Lab 1",
        "room": null,
        "type": "Laboratory",
        "total_inventory": 6
      }
    ]
  }
  ```

#### `get_inventory_by_facility`
- Returns all inventory items in a specific facility
- Parameters: `facility_id`
- Uses same format as the existing `list` action
- **Response Format:**
  ```json
  {
    "success": true,
    "inventory": [
      {
        "inventory_id": 1,
        "name": "System Unit",
        "item_code": "IT-001",
        "quantity": 5,
        ...
      }
    ]
  }
  ```

---

### 2. **Frontend - HTML Structure**
**File:** `public/pages/inventory_management.php`

**Changes:**
- Removed direct inventory table display
- Added three separate views (hidden/shown based on navigation)
- Added breadcrumb navigation component
- Maintains all existing modals (Add/Edit Inventory, Components, Detail View)

**New HTML Sections:**

#### Breadcrumb Navigation
```html
<div class="breadcrumb-nav" id="breadcrumb">
    <div class="breadcrumb-item active" id="breadcrumb-home">
        <i class="fas fa-home"></i> Departments
    </div>
    <div class="breadcrumb-item" id="breadcrumb-facility">
        <i class="fas fa-chevron-right"></i> <span id="breadcrumb-facility-text"></span>
    </div>
    <div class="breadcrumb-item" id="breadcrumb-inventory">
        <i class="fas fa-chevron-right"></i> Inventory
    </div>
</div>
```

#### Three Views
1. **departmentsView** - Table of all departments
2. **facilitiesView** - Table of rooms/labs in selected department
3. **inventoryView** - Table of items in selected facility

---

### 3. **Frontend - New JavaScript File**
**File:** `public/assets/js/inventory_hierarchy.js` (NEW)

**Functions:**

#### `loadDepartments()`
- Fetches departments from API
- Renders department table with statistics
- Makes rows clickable

#### `viewDepartmentFacilities(deptId, deptName)`
- Called when user clicks a department
- Shows the facilities view
- Loads facilities for that department

#### `showFacilitiesView(deptId, deptName)`
- Displays facilities table
- Updates breadcrumb navigation
- Handles facility row clicks

#### `viewFacilityInventory(facilityId, facilityName)`
- Called when user clicks a facility
- Shows the inventory view
- Loads inventory items for that facility

#### `showDepartmentsView()`
- Returns to departments view
- Resets navigation state
- Called when user clicks home in breadcrumb

#### `attachInventoryTableListeners()`
- Dispatches custom event to notify inventory_management.js
- Allows re-attachment of event listeners on dynamically loaded content

---

### 4. **Frontend - Modified JavaScript File**
**File:** `public/assets/js/inventory_management.js`

**Modifications:**

#### Initialization Changes
- Removed automatic `loadInventory()` call
- Added event listener for `inventoryTableLoaded` event
- Delayed attachment of table listeners to ensure DOM is ready

#### New Function: `attachTableListeners()`
- Handles click events for View, Edit, Delete buttons
- Ensures listeners work with dynamically loaded inventory
- Separated from inline event listener for reusability

#### Modified: `addInventoryBtn` Click Handler
- Now pre-selects current facility from hierarchy
- Uses `window.inventoryHierarchy.getCurrentFacilityId()`
- Pre-populates facility and user dropdowns

#### Modified: Inventory Form Submission
- After saving, reloads current facility's inventory (if in hierarchy mode)
- Falls back to full inventory list if not navigating through hierarchy

#### Modified: Delete Handler
- Reloads current facility's inventory after deletion
- Maintains navigation context

---

### 5. **Frontend - Styling**
**File:** `public/assets/css/inventory_management.css`

**New Styles Added:**

#### Breadcrumb Navigation
```css
.breadcrumb-nav {
    background: white;
    padding: 1rem 2rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    animation: slideDown 0.5s ease;
}

.breadcrumb-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #64748b;
    font-size: 0.95rem;
    transition: all 0.2s ease;
}

.breadcrumb-item.active {
    color: #1e293b;
    font-weight: 600;
}

.breadcrumb-item[style*="cursor: pointer"]:hover {
    color: #3b82f6;
}
```

#### Stat Badges
```css
.stat-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #e0f2fe;
    color: #0369a1;
    padding: 0.4rem 0.75rem;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.9rem;
    min-width: 40px;
}

.type-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #fce7f3;
    color: #be185d;
    padding: 0.4rem 0.75rem;
    border-radius: 6px;
    font-weight: 500;
    font-size: 0.85rem;
}

.inventory-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #dbeafe;
    color: #1e40af;
    padding: 0.4rem 0.75rem;
    border-radius: 6px;
    font-weight: 500;
    font-size: 0.9rem;
}
```

#### Row Hover Effects
```css
.department-row:hover,
.facility-row:hover {
    background-color: #f8fafc;
    box-shadow: inset 0 0 0 1px #e2e8f0;
}
```

#### Action Buttons
```css
.action-btn.view-facilities,
.action-btn.view-inventory {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
    padding: 0.6rem 1rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.action-btn.view-facilities:hover,
.action-btn.view-inventory:hover {
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}
```

---

## Navigation Flow

### User Journey:
1. **Page Load** → Shows Departments View
   - All departments with lab, room, and inventory counts
   - Click on department or its action button → proceeds to step 2

2. **Department Selected** → Shows Facilities View
   - All rooms and labs within selected department
   - Breadcrumb shows: Home > Department Name
   - Click on facility or its action button → proceeds to step 3
   - Click breadcrumb home → returns to step 1

3. **Facility Selected** → Shows Inventory View
   - All inventory items in selected facility
   - Breadcrumb shows: Home > Department Name > Inventory
   - Can Add/Edit/Delete inventory items
   - Click facility breadcrumb → returns to step 2
   - Click home breadcrumb → returns to step 1

4. **After Save/Delete** → Stays in Inventory View (Facility)
   - Automatically reloads inventory for current facility
   - Maintains user's navigation context

---

## Global Export

The `inventory_hierarchy.js` exports these functions via `window.inventoryHierarchy`:

```javascript
window.inventoryHierarchy = {
    viewFacilityInventory,      // View inventory by facility
    showDepartmentsView,        // Go back to departments view
    getCurrentFacilityId        // Get current selected facility ID
};
```

These allow `inventory_management.js` to:
- Pre-select current facility when adding inventory
- Reload current facility's inventory after operations
- Maintain navigation context

---

## Design & Theme

- **Color Scheme:** Maintains existing system colors
  - Primary: #3b82f6 (Blue)
  - Green: #16a34a (Actions)
  - Gray: #64748b (Secondary text)
  
- **Animations:** Smooth fade-in and slide-down transitions
- **Spacing:** Consistent with existing design system
- **Typography:** Uses existing Inter font family

---

## Database Requirements

Assumes the following tables exist with appropriate columns:

- `facilities`: `department_id`, `department_name`, `laboratory`, `room`, `type`
- `inventory`: `inventory_id`, `name`, `facility_id`, `quantity`, ...
- `items`: `item_id`, `item_name`
- `user`: `user_id`, `Email`, `role`, `department_id`

---

## Testing Checklist

- [x] Department list displays correctly
- [x] Department statistics (labs, rooms, inventory) calculate correctly
- [x] Clicking department shows facilities
- [x] Breadcrumb updates correctly during navigation
- [x] Clicking breadcrumb returns to previous view
- [x] Facility shows correct inventory count
- [x] Clicking facility shows inventory items
- [x] Add inventory pre-selects current facility
- [x] Save/Delete operations reload current facility inventory
- [x] All modals still function correctly
- [x] Theme and styling matches existing design

---

## Notes for Future Enhancements

1. Could add search/filter functionality at each level
2. Could add batch actions for inventory items
3. Could add export functionality (PDF/Excel)
4. Could add inventory movement tracking
5. Could add real-time updates when inventory changes

