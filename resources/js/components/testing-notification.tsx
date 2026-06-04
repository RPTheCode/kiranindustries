import React, { useState, useEffect } from 'react';
import { X, AlertTriangle } from 'lucide-react';

export function TestingNotification() {
    const [isVisible, setIsVisible] = useState(false);

    useEffect(() => {
        // Check localStorage to see if user has already closed it
        const isClosed = localStorage.getItem('hideTestingNotice');
        if (!isClosed) {
            setIsVisible(true);
        }
    }, []);

    const handleClose = () => {
        setIsVisible(false);
        localStorage.setItem('hideTestingNotice', 'true');
    };

    if (!isVisible) return null;

    return (
        <div className="fixed top-0 left-0 right-0 z-[9999] animate-in fade-in slide-in-from-top duration-500">
            <div className="bg-[#fef9c3] border-b border-yellow-200 px-4 py-1 shadow-sm flex items-center justify-between">
                <div className="flex items-center gap-2 max-w-[95%] mx-auto">
                    <AlertTriangle className="w-3.5 h-3.5 text-yellow-700 shrink-0" />
                    <p className="text-[12px] font-bold text-yellow-900 leading-tight">
                        This is a testing version of the software. Some features may be under development or subject to change.
                    </p>
                </div>
                <button 
                    onClick={handleClose}
                    className="p-1 hover:bg-yellow-200/50 rounded-full text-yellow-700 transition-colors"
                    aria-label="Close notification"
                >
                    <X className="w-3.5 h-3.5" />
                </button>
            </div>
        </div>
    );
}
