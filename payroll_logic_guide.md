# Payroll & Salary Structure Guide

This document explains how the Kiran Industries ERP handles salary calculations, minimum wages, and employee-specific configurations.

## 1. Salary Calculation Structures

### A. Monthly Workers (26 Days)

- **Target**: Staff and regular workers.
- **Logic**:
    - `Basic Salary` = Total amount for a full month (e.g., ₹14,640).
    - `Per Day Rate` = `Basic Salary` / 26 (e.g., ₹563.07).
    - `Total Payable` = `Per Day Rate` \* (Present Days + Paid Leaves).

### B. Daily Workers (1 Day)

- **Target**: Casual or daily-wage workers.
- **Logic**:
    - `Basic Salary` = The amount earned for **one single day** (e.g., ₹500).
    - `Daily Option` = Enabled (Switch is ON).
    - `Total Payable` = `Basic Salary` \* (Present Days).

---

## 2. Minimum Wages (Government Rule)

**Minimum Wage** is the legally mandated lowest salary an employer must pay.

- **Usage in System**:
    - If the government sets a minimum wage of ₹10,000 but your worker earns ₹9,000, you must track the "Minimum Wage" field.
    - **PF Calculation**: Often, PF is calculated on the `Minimum Wage` or `Basic Salary`, whichever is higher (up to a limit of ₹15,000).
    - **Compliance**: The `Minimum Wage Per Day` field helps the system verify if you are paying according to labor laws.

---

## 3. Individual Week-Off Management

Every employee has their own `Individual Week Off` (e.g., Sunday, Monday, or Friday).

- **How it works**:
    1.  **Attendance Check**: During payroll, the system looks at the date and compares it to the employee's `week_off`.
    2.  **Paid Week-off**: Usually, for monthly workers, the week-off is a **Paid Holiday** (if they were present the day before and after).
    3.  **Working on Week-off**: If an employee works on their designated week-off, it is automatically counted as **Overtime (P.I.)** or a **Double Duty**, depending on your settings.

---

## 4. How to do it properly in the UI

### Step 1: Employee Registration

- Set the `Daily Option` correctly.
- Select the specific `Individual Week Off` for that worker.
- Enter the `Basic Salary`.

### Step 2: Salary Setup (Salary Management)

- The system automatically calculates the `Per Day Salary` based on the 1 or 26 day rule.
- Enter `Minimum Wages` if you need to calculate PF based on government norms.

### Step 3: Run Payroll

- The system will fetch attendance.
- It will check the `week_off` for each day.
- It will multiply the correct `Per Day Rate` by the actual working days.

---

**Note**: This logic is now implemented in your `EmployeesImport.php` and `edit.tsx` files.
