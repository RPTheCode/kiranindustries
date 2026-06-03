import * as React from "react"
import { Check, ChevronsUpDown, X } from "lucide-react"

import { cn } from "@/lib/utils"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover"
import { Checkbox } from "@/components/ui/checkbox"
import { ScrollArea } from "@/components/ui/scroll-area"

interface MultiSelectFieldProps {
    field: {
        name: string
        label: string
        options?: { value: string; label: string }[]
        placeholder?: string
    }
    formData: any
    handleChange: (name: string, value: any) => void
}

export function MultiSelectField({ field, formData, handleChange }: MultiSelectFieldProps) {
    const [open, setOpen] = React.useState(false)
    const [searchQuery, setSearchQuery] = React.useState("")
    const rawValue = formData[field.name]
    const selectedValues = Array.isArray(rawValue) ? rawValue : []

    const handleSelect = (value: string) => {
        const newSelectedValues = selectedValues.includes(value)
            ? selectedValues.filter((item: string) => item !== value)
            : [...selectedValues, value]

        handleChange(field.name, newSelectedValues)
    }

    const handleRemove = (value: string, e: React.MouseEvent) => {
        e.stopPropagation()
        const newSelectedValues = selectedValues.filter((item) => item !== value)
        handleChange(field.name, newSelectedValues)
    }

    const filteredOptions = field.options?.filter(option => {
        if (!searchQuery) return true;
        const query = searchQuery.toLowerCase().trim();
        return option.label && option.label.toLowerCase().includes(query);
    }) || []

    return (
        <div className="flex flex-col gap-2">
            <Popover open={open} onOpenChange={setOpen} modal={true}>
                <PopoverTrigger asChild>
                    <Button
                        variant="outline"
                        role="combobox"
                        aria-expanded={open}
                        className="w-full justify-between h-auto min-h-10 py-2"
                    >
                        <div className="flex flex-wrap gap-1 items-center">
                            {selectedValues.length > 0 ? (
                                selectedValues.map((value) => {
                                    const option = field.options?.find((opt) => opt.value === value)
                                    return (
                                        <Badge key={value} variant="secondary" className="mr-1">
                                            {option?.label || value}
                                            <button
                                                className="ml-1 ring-offset-background rounded-full outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                                onKeyDown={(e) => {
                                                    if (e.key === "Enter") {
                                                        handleRemove(value, e as any)
                                                    }
                                                }}
                                                onMouseDown={(e) => {
                                                    e.preventDefault()
                                                    e.stopPropagation()
                                                }}
                                                onClick={(e) => handleRemove(value, e)}
                                            >
                                                <X className="h-3 w-3 text-muted-foreground hover:text-foreground" />
                                            </button>
                                        </Badge>
                                    )
                                })
                            ) : (
                                <span className="text-muted-foreground font-normal">
                                    {field.placeholder || `Select ${field.label}...`}
                                </span>
                            )}
                        </div>
                        <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent 
                    className="w-[var(--radix-popover-trigger-width)] p-0 z-[60000]" 
                    align="start"
                    usePortal={false}
                >
                    <div className="p-2 border-b">
                        <input
                            autoFocus
                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                            placeholder="Search..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                        />
                    </div>
                    <ScrollArea className="h-64 w-full p-4">
                        <div className="flex flex-col gap-2">
                            {filteredOptions.length > 0 ? (
                                filteredOptions.map((option) => (
                                    <div
                                        key={option.value}
                                        className="flex items-center space-x-2 p-2 hover:bg-accent rounded-md cursor-pointer"
                                        onClick={(e) => {
                                            e.preventDefault();
                                            e.stopPropagation();
                                            handleSelect(option.value);
                                        }}
                                    >
                                        <Checkbox
                                            id={`${field.name}-${option.value}`}
                                            checked={selectedValues.includes(option.value)}
                                            // The parent div handles the state toggle.
                                            // onCheckedChange is omitted to prevent Radix from interfering or double-firing.
                                        />
                                        <label
                                            htmlFor={`${field.name}-${option.value}`}
                                            className="text-sm font-medium leading-none cursor-pointer w-full py-1 pointer-events-none"
                                        >
                                            {option.label}
                                        </label>
                                    </div>
                                ))
                            ) : (
                                <p className="text-sm text-muted-foreground text-center py-4">
                                    No results found
                                </p>
                            )}
                        </div>
                    </ScrollArea>
                </PopoverContent>
            </Popover>
        </div>
    )
}
