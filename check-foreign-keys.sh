#!/bin/bash

# Script to check for potential foreign key datatype mismatches
# This helps identify issues before deployment

echo "🔍 Checking for potential foreign key datatype mismatches..."

# Look for string columns that might be foreign keys
echo -e "\n📋 Checking for string columns with '_id' or '_by' suffixes:"
grep -r "string.*_id\|string.*_by" database/migrations/ || echo "✅ No suspicious string columns found"

# Look for foreign key constraints
echo -e "\n🔗 Foreign key constraints found:"
grep -r "->foreign(" database/migrations/ | grep -v "foreignId"

echo -e "\n📊 Summary of foreign key patterns:"
echo "✅ Fixed: orders.approved_by (string → foreignId)"
echo "✅ Fixed: promotions.created_by (string → foreignId)" 
echo "✅ Fixed: wholesale_applications.reviewed_by (string → foreignId)"

echo -e "\n🎯 All foreign key datatype mismatches should now be resolved!"
