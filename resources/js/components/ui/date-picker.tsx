"use client"

import * as React from "react"
import { format } from "date-fns"
import { Calendar as CalendarIcon } from "lucide-react"

import { cn } from "@/lib/utils"
import { Button } from "@/components/ui/button"
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover"
import { Input } from "@/components/ui/input"

interface DatePickerProps {
  selected?: Date
  onSelect?: (date: Date | undefined) => void
  onChange?: (date: Date | undefined) => void
  placeholder?: string
  disabled?: boolean
  className?: string
  inputClassName?: string
  /** yyyy-MM-dd — e.g. today to block future dates */
  max?: string
  /** yyyy-MM-dd */
  min?: string
}

export function DatePicker({
  selected,
  onSelect,
  onChange,
  placeholder = "Pick a date",
  disabled = false,
  className,
  inputClassName,
  max,
  min,
}: DatePickerProps) {
  const [date, setDate] = React.useState<string>(selected ? format(selected, 'yyyy-MM-dd') : '');
  
  const handleDateChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setDate(e.target.value);
    
    if (e.target.value) {
      const [y, m, d] = e.target.value.split('-').map(Number);
      const newDate = new Date(y, m - 1, d);
      if (onSelect) onSelect(newDate);
      if (onChange) onChange(newDate);
    } else {
      if (onSelect) onSelect(undefined);
      if (onChange) onChange(undefined);
    }
  };
  
  React.useEffect(() => {
    if (selected) {
      setDate(format(selected, 'yyyy-MM-dd'));
    } else {
      setDate('');
    }
  }, [selected]);

  return (
    <div className={cn("relative group w-full", className)}>
      <CalendarIcon className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground z-10 pointer-events-none group-hover:text-primary transition-colors" />
      <Input
        type="date"
        value={date}
        max={max}
        min={min}
        title={placeholder}
        onChange={handleDateChange}
        onClick={(e) => e.currentTarget.showPicker?.()}
        className={cn(
          "pl-10 w-full h-9 cursor-pointer relative z-0 text-sm border-slate-200 bg-white",
          "[&::-webkit-calendar-picker-indicator]:cursor-pointer [&::-webkit-calendar-picker-indicator]:hover:bg-slate-100 [&::-webkit-calendar-picker-indicator]:rounded-md [&::-webkit-calendar-picker-indicator]:p-1",
          !date && "text-slate-400",
          inputClassName
        )}
        disabled={disabled}
      />
    </div>
  )
}