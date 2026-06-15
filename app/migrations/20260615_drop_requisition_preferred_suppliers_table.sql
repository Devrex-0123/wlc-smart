-- Migration: Drop the redundant requisition_preferred_suppliers table
-- Reason: Database normalization - all data is now stored in requisition_preferred_supplier_item table
-- The JSON columns quoted_prices and quote_photos were denormalized duplicates that are now aggregated
-- from requisition_preferred_supplier_item on-demand
-- Date: 2025-04-15

DROP TABLE IF EXISTS `requisition_preferred_suppliers`;
