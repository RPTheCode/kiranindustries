import React, { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import axios from 'axios';

// ... inside the file, before default export
const PaginatedTable = ({ data, title, colorClass, badgeClass, columns }: any) => {
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const perPage = 20;

    const filtered = data.filter((row: any) => 
        Object.values(row).some((val: any) => 
            String(val).toLowerCase().includes(search.toLowerCase())
        )
    );

    const pages = Math.ceil(filtered.length / perPage);
    const currentData = filtered.slice((page - 1) * perPage, page * perPage);

    return (
        <div className={`p-6 ${colorClass} flex flex-col h-full`}>
            <div className="flex items-center justify-between mb-4">
                <h3 className={`text-lg font-semibold flex items-center ${badgeClass.text}`}>
                    <span className={`w-3 h-3 rounded-full ${badgeClass.bg} mr-2`}></span>
                    {title} ({filtered.length})
                </h3>
            </div>
            
            <div className="mb-4">
                <input 
                    type="text" 
                    placeholder="Search employees..." 
                    className="w-full px-4 py-2 border border-gray-200 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    value={search}
                    onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                />
            </div>

            <div className="overflow-x-auto flex-grow rounded-lg border border-gray-200">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50 sticky top-0 z-10">
                        <tr>
                            {columns.map((col: any) => (
                                <th key={col.key} className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">
                                    {col.label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                        {currentData.length > 0 ? currentData.map((item: any, idx: number) => (
                            <tr key={idx} className="hover:bg-gray-50 transition-colors">
                                {columns.map((col: any) => (
                                    <td key={col.key} className="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                                        {col.render ? col.render(item[col.key], item) : item[col.key]}
                                    </td>
                                ))}
                            </tr>
                        )) : (
                            <tr>
                                <td colSpan={columns.length} className="px-4 py-8 text-center text-sm text-gray-500 italic">No results found.</td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {pages > 1 && (
                <div className="flex items-center justify-between mt-4 pt-4 border-t border-gray-200">
                    <button 
                        onClick={() => setPage(p => Math.max(1, p - 1))}
                        disabled={page === 1}
                        className="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        Previous
                    </button>
                    <span className="text-sm text-gray-600 font-medium">Page {page} of {pages}</span>
                    <button 
                        onClick={() => setPage(p => Math.min(pages, p + 1))}
                        disabled={page === pages}
                        className="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        Next
                    </button>
                </div>
            )}
        </div>
    );
};

export default function EmployeeCheck() {
    const [file, setFile] = useState<File | null>(null);
    const [loading, setLoading] = useState(false);
    const [results, setResults] = useState<{ found: any[], essl_found: any[], not_found: any[], download_token?: string } | null>(null);
    const [error, setError] = useState('');

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files[0]) {
            setFile(e.target.files[0]);
            setError('');
        }
    };

    const handleProcess = async () => {
        if (!file) {
            setError('Please select an Excel file first.');
            return;
        }

        setLoading(true);
        setError('');
        setResults(null);

        const formData = new FormData();
        formData.append('file', file);

        try {
            const response = await axios.post('/employee-check/process', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            setResults(response.data);
        } catch (err: any) {
            console.error(err);
            setError(err.response?.data?.error || 'An error occurred while processing the file.');
        } finally {
            setLoading(false);
        }
    };

    const handleDownload = () => {
        if (!results || !results.download_token) return;
        window.location.href = `/employee-check/download?token=${results.download_token}`;
    };

    return (
        <div className="min-h-screen bg-gray-50 py-8 px-4 sm:px-6 lg:px-8">
            <Head title="Employee Verification" />

            <div className="max-w-7xl mx-auto space-y-8">
                {/* Header */}
                <div className="bg-white rounded-2xl shadow-sm p-8 border border-gray-100 text-center">
                    <h1 className="text-3xl font-bold text-gray-900 mb-2">Employee Data Verification</h1>
                    <p className="text-gray-500">Upload an Excel file to verify if employees exist in the system across all branches or in ESSL.</p>
                </div>

                {/* Upload Section */}
                <div className="bg-white rounded-2xl shadow-sm p-8 border border-gray-100">
                    <div className="flex flex-col items-center justify-center space-y-6">
                        <div className="w-full max-w-xl">
                            <label className="flex justify-center w-full h-32 px-4 transition bg-white border-2 border-gray-300 border-dashed rounded-xl appearance-none cursor-pointer hover:border-indigo-600 focus:outline-none">
                                <span className="flex items-center space-x-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" className="w-6 h-6 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="2">
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                    <span className="font-medium text-gray-600">
                                        {file ? file.name : 'Drop Excel file here, or click to browse'}
                                    </span>
                                </span>
                                <input type="file" name="file_upload" className="hidden" accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" onChange={handleFileChange} />
                            </label>
                        </div>
                        
                        {error && <div className="text-red-500 text-sm font-medium">{error}</div>}

                        <button 
                            onClick={handleProcess} 
                            disabled={loading || !file}
                            className={`px-8 py-3 font-semibold text-white transition-all rounded-lg shadow-md hover:shadow-lg ${loading || !file ? 'bg-indigo-400 cursor-not-allowed' : 'bg-indigo-600 hover:bg-indigo-700 active:scale-95'}`}
                        >
                            {loading ? (
                                <span className="flex items-center">
                                    <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Processing...
                                </span>
                            ) : 'Verify Employees'}
                        </button>
                    </div>
                </div>

                {/* Results Section */}
                {results && (
                    <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden animate-fade-in-up">
                        <div className="p-6 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
                            <h2 className="text-xl font-bold text-gray-800">Verification Results</h2>
                            <button 
                                onClick={handleDownload}
                                className="flex items-center px-5 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors shadow-sm"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                    <path fillRule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clipRule="evenodd" />
                                </svg>
                                Download Excel
                            </button>
                        </div>
                        
                        <div className="grid md:grid-cols-3 gap-0 divide-y md:divide-y-0 md:divide-x divide-gray-200 min-h-[600px]">
                            
                            <PaginatedTable 
                                data={results.found || []}
                                title="Found in DB"
                                colorClass="bg-white"
                                badgeClass={{ text: 'text-green-700', bg: 'bg-green-500' }}
                                columns={[
                                    { key: 'code', label: 'Code', render: (val: string) => <span className="font-semibold">{val}</span> },
                                    { key: 'name', label: 'Name' },
                                    { key: 'branch', label: 'Branch', render: (val: string) => <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">{val}</span> }
                                ]}
                            />

                            <PaginatedTable 
                                data={results.essl_found || []}
                                title="Found in ESSL"
                                colorClass="bg-yellow-50/30"
                                badgeClass={{ text: 'text-yellow-700', bg: 'bg-yellow-500' }}
                                columns={[
                                    { key: 'code', label: 'Code', render: (val: string) => <span className="font-semibold">{val}</span> },
                                    { key: 'name', label: 'Name' },
                                    { key: 'status', label: 'Status', render: () => <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Needs Import</span> }
                                ]}
                            />

                            <PaginatedTable 
                                data={results.not_found || []}
                                title="Not Found"
                                colorClass="bg-red-50/30"
                                badgeClass={{ text: 'text-red-700', bg: 'bg-red-500' }}
                                columns={[
                                    { key: 'code', label: 'Code', render: (val: string) => <span className="font-semibold">{val}</span> },
                                    { key: 'status', label: 'Status', render: () => <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Missing</span> }
                                ]}
                            />

                        </div>
                    </div>
                )}
            </div>

            <style>{`
                @keyframes fadeInUp {
                    from { opacity: 0; transform: translateY(20px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                .animate-fade-in-up {
                    animation: fadeInUp 0.4s ease-out forwards;
                }
            `}</style>
        </div>
    );
}
