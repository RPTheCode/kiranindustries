import * as React from "react"
import { Check, ChevronsUpDown, X } from "lucide-react"
import { useTranslation } from "react-i18next"

import { cn } from "@/lib/utils"
import { Button } from "@/components/ui/button"
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover"
import { Badge } from "@/components/ui/badge"
import { Checkbox } from "@/components/ui/checkbox"

export type Option = {
    label: string
    value: string
}

interface MultiSelectProps {
    options: Option[]
    selected: string[]
    onChange: (selected: string[]) => void
    placeholder?: string
    className?: string
}

export function MultiSelect({
    options,
    selected,
    onChange,
    placeholder = "Select items...",
    className,
}: MultiSelectProps) {
    const { t } = useTranslation()
    const [open, setOpen] = React.useState(false)
    const [searchQuery, setSearchQuery] = React.useState("")
    const safeSelected = Array.isArray(selected) ? selected : []

    const handleUnselect = (item: string) => {
        onChange(safeSelected.filter((i) => i !== item))
    }

    const filteredOptions = React.useMemo(() => {
        if (!searchQuery) return options;
        const lowerQuery = searchQuery.toLowerCase();
        return options.filter((option) => option.label.toLowerCase().includes(lowerQuery));
    }, [options, searchQuery]);

    return (
        <Popover open={open} onOpenChange={setOpen} modal={false}>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    className={cn("w-full justify-between h-auto min-h-10 py-2 hover:bg-background", className)}
                >
                    <div className="flex gap-1 flex-wrap items-center">
                        {safeSelected.length > 0 ? (
                            safeSelected.length > 3 ? (
                                <Badge variant="secondary" className="mr-1 mb-1">
                                    {safeSelected.length} {t('selected')}
                                </Badge>
                            ) : (
                                safeSelected.map((item) => (
                                    <Badge
                                        key={item}
                                        variant="secondary"
                                        className="mr-1 mb-1"
                                        onClick={(e) => {
                                            e.stopPropagation()
                                            handleUnselect(item)
                                        }}
                                    >
                                        {options.find((option) => option.value === item)?.label || item}
                                        <button
                                            className="ml-1 ring-offset-background rounded-full outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                            onKeyDown={(e) => {
                                                if (e.key === "Enter") {
                                                    handleUnselect(item)
                                                }
                                            }}
                                            onMouseDown={(e) => {
                                                e.preventDefault()
                                                e.stopPropagation()
                                            }}
                                            onClick={(e) => {
                                                e.preventDefault()
                                                e.stopPropagation()
                                                handleUnselect(item)
                                            }}
                                        >
                                            <X className="h-3 w-3 text-muted-foreground hover:text-foreground" />
                                        </button>
                                    </Badge>
                                ))
                            )
                        ) : (
                            <span className="text-muted-foreground">{placeholder}</span>
                        )}
                    </div>
                    <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-[var(--radix-popover-trigger-width)] p-0" align="start" usePortal={false}>
                <div className="flex items-center border-b px-3">
                    <input
                        autoFocus
                        className="flex h-10 w-full rounded-md bg-transparent py-3 text-sm outline-none placeholder:text-muted-foreground border-none focus-visible:ring-0 px-0 shadow-none"
                        placeholder="Search..."
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                    />
                </div>
                <div className="w-full p-2 max-h-60 overflow-y-auto">
                    {options.length > 0 && !searchQuery && (
                        <div
                            className={cn(
                                "relative flex cursor-default select-none items-center rounded-sm px-2 py-1.5 text-sm font-bold border-b mb-1 outline-none transition-colors hover:bg-accent hover:text-accent-foreground",
                                safeSelected.length === options.length ? "bg-accent text-accent-foreground" : ""
                            )}
                            onClick={(e) => {
                                e.preventDefault();
                                e.stopPropagation();
                                if (safeSelected.length === options.length) {
                                    onChange([]);
                                } else {
                                    onChange(options.map(o => o.value));
                                }
                            }}
                        >
                            <div className={cn("mr-2 flex h-4 w-4 items-center justify-center border-primary")}>
                                {safeSelected.length === options.length ? <Check className="h-4 w-4" /> : null}
                            </div>
                            <span>{t('Select All')}</span>
                        </div>
                    )}
                    {filteredOptions.length === 0 && (
                        <div className="py-6 text-center text-sm text-muted-foreground">
                            No options found.
                        </div>
                    )}
                    {filteredOptions.map((option) => {
                        const isSelected = safeSelected.includes(option.value);
                        return (
                            <div
                                key={option.value}
                                className={cn(
                                    "relative flex cursor-default select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none transition-colors hover:bg-accent hover:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50",
                                    isSelected ? "bg-accent text-accent-foreground" : ""
                                )}
                                onClick={(e) => {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    if (isSelected) {
                                        onChange(safeSelected.filter((item) => item !== option.value))
                                    } else {
                                        onChange([...safeSelected, option.value])
                                    }
                                }}
                            >
                                <div className={cn("mr-2 flex h-4 w-4 items-center justify-center border-primary")}>
                                    {isSelected ? <Check className="h-4 w-4" /> : null}
                                </div>
                                <span>{option.label}</span>
                            </div>
                        )
                    })}
                </div>
            </PopoverContent>
        </Popover>
    )
}
