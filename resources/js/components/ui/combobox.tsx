import * as React from "react"
import { Check, ChevronsUpDown, Search } from "lucide-react"

import { cn } from "@/lib/utils"
import { Button } from "@/components/ui/button"
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover"
import { Input } from "@/components/ui/input"

export type ComboboxOption = {
    label: string
    value: string
}

interface ComboboxProps {
    options: ComboboxOption[]
    value?: string
    onChange: (value: string) => void
    placeholder?: string
    searchPlaceholder?: string
    emptyText?: string
    className?: string
    modal?: boolean
    disabled?: boolean
    variant?: 'default' | 'ghost'
}

export function Combobox({
    options,
    value,
    onChange,
    placeholder = "Select item...",
    searchPlaceholder = "Search...",
    emptyText = "No option found.",
    className,
    modal = false,
    disabled = false,
    variant = 'default',
}: ComboboxProps) {
    const [open, setOpen] = React.useState(false)
    const [searchQuery, setSearchQuery] = React.useState("")

    const filteredOptions = options.filter((option) =>
        option.label.toLowerCase().includes(searchQuery.toLowerCase())
    )

    const selectedLabel = value
        ? options.find((option) => option.value === value)?.label
        : placeholder

    return (
        <Popover open={open} onOpenChange={setOpen} modal={false}>
            <PopoverTrigger asChild>
                <Button
                    variant={variant === 'ghost' ? 'ghost' : 'outline'}
                    role="combobox"
                    aria-expanded={open}
                    className={cn(
                        'w-full min-w-0 justify-between font-normal',
                        variant === 'ghost' && 'font-semibold text-slate-800 hover:bg-slate-100/80 dark:text-slate-100',
                        !value && 'text-muted-foreground',
                        className
                    )}
                    disabled={disabled}
                >
                    <span className="truncate text-left flex-1">{selectedLabel}</span>
                    <ChevronsUpDown className="ml-1.5 h-3.5 w-3.5 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent 
                className="w-[var(--radix-popover-trigger-width)] p-0" 
                align="start"
                sideOffset={4}
                usePortal={false}
            >
                <div className="flex items-center border-b px-3">
                    <Search className="mr-2 h-4 w-4 shrink-0 opacity-50" />
                    <input
                        autoFocus
                        className="flex h-10 w-full rounded-md bg-transparent py-3 text-sm outline-none placeholder:text-muted-foreground border-none focus-visible:ring-0 px-0 shadow-none"
                        placeholder={searchPlaceholder}
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                    />
                </div>
                <div className="max-h-[200px] overflow-y-auto p-1">
                    {filteredOptions.length === 0 ? (
                        <div className="py-6 text-center text-sm text-muted-foreground">
                            {emptyText}
                        </div>
                    ) : (
                        filteredOptions.map((option) => (
                            <div
                                key={option.value}
                                className={cn(
                                    "relative flex cursor-default select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none hover:bg-accent hover:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50",
                                    value === option.value ? "bg-accent text-accent-foreground" : ""
                                )}
                                onClick={() => {
                                    onChange(option.value === value ? "" : option.value)
                                    setOpen(false)
                                }}
                            >
                                <Check
                                    className={cn(
                                        "mr-2 h-4 w-4",
                                        value === option.value ? "opacity-100" : "opacity-0"
                                    )}
                                />
                                {option.label}
                            </div>
                        ))
                    )}
                </div>
            </PopoverContent>
        </Popover>
    )
}
