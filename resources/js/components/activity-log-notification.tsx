import React, { useEffect, useState } from 'react';
import { Bell, Activity } from 'lucide-react';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import axios from 'axios';

export function ActivityLogNotification() {
    const { t } = useTranslation();
    const [logs, setLogs] = useState<any[]>([]);

    useEffect(() => {
        const fetchLogs = async () => {
            try {
                const response = await axios.get(route('api.activity-logs.latest'));
                setLogs(response.data);
            } catch (error) {
                console.error("Failed to fetch activity logs", error);
            }
        };
        fetchLogs();
    }, []);

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="relative cursor-pointer">
                    <Bell className="h-5 w-5 text-gray-600 dark:text-gray-300" />
                    {logs.length > 0 && (
                        <span className="absolute top-1 right-1 flex h-2.5 w-2.5">
                            <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                            <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80 p-0">
                <div className="bg-gray-50/50 dark:bg-gray-800/50 p-3 border-b flex items-center justify-between">
                    <h3 className="font-semibold text-sm flex items-center gap-2">
                        <Activity className="h-4 w-4 text-primary" />
                        {t("Latest Activity")}
                    </h3>
                </div>
                <div className="max-h-80 overflow-y-auto">
                    {logs.length > 0 ? logs.map((log: any) => (
                        <DropdownMenuItem key={log.id} className="flex flex-col items-start p-3 border-b last:border-0 cursor-default focus:bg-gray-50 dark:focus:bg-gray-800/80">
                            <div className="flex w-full justify-between items-center mb-1">
                                <span className="font-semibold text-xs text-primary">{log.module}</span>
                                <span className="text-[10px] text-gray-400">
                                    {new Date(log.created_at).toLocaleDateString()}
                                </span>
                            </div>
                            <p className="text-sm text-gray-600 dark:text-gray-300 line-clamp-2 leading-snug">
                                {log.description}
                            </p>
                            <span className="text-[10px] text-gray-400 mt-1 block">
                                By {log.user_name}
                            </span>
                        </DropdownMenuItem>
                    )) : (
                        <div className="p-4 text-center text-sm text-gray-500">
                            {t("No new activity")}
                        </div>
                    )}
                </div>
                <div className="p-2 border-t bg-gray-50/50 dark:bg-gray-800/50">
                    <Button 
                        variant="ghost" 
                        size="sm" 
                        className="w-full text-xs text-primary hover:bg-primary/10 cursor-pointer"
                        onClick={() => router.visit(route('hr.activity-logs.index'))}
                    >
                        {t("Show More")}
                    </Button>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
