import React, { useState } from 'react';
import { useForm, usePage, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Pagination } from '@/components/ui/pagination';
import { SearchAndFilterBar } from '@/components/ui/search-and-filter-bar';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { RichTextEditor } from '@/components/ui/rich-text-editor';
import { Switch } from '@/components/ui/switch';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Plus, Edit, Trash2, Eye, EyeOff, Search } from 'lucide-react';
import { PageTemplate } from '@/components/page-template';
import { CrudTable } from '@/components/CrudTable';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { toast } from '@/components/custom-toast';
import { Toaster } from '@/components/ui/toaster';

interface CustomPage {
  id: number;
  title: string;
  slug: string;
  content: string;
  meta_title?: string;
  meta_description?: string;
  is_active: boolean;
  sort_order: number;
}

interface PageProps {
  pages: CustomPage[];
  flash?: {
    success?: string;
    error?: string;
  };
}

export default function CustomPagesIndex() {
  const { pages, flash, filters: pageFilters = {} } = usePage<PageProps>().props;
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [editingPage, setEditingPage] = useState<CustomPage | null>(null);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [deletingPage, setDeletingPage] = useState<CustomPage | null>(null);
  const [searchTerm, setSearchTerm] = useState(pageFilters.search || '');

  const { data, setData, post, put, processing, errors, reset } = useForm({
    title: '',
    content: '',
    meta_title: '',
    meta_description: '',
    is_active: true,
    sort_order: 0
  });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    
    if (editingPage) {
      put(route('landing-page.custom-pages.update', editingPage.id), {
        onSuccess: () => {
          setEditingPage(null);
          reset();
          toast.success('Page updated successfully!');
        }
      });
    } else {
      post(route('landing-page.custom-pages.store'), {
        onSuccess: () => {
          setIsCreateOpen(false);
          reset();
          toast.success('Page created successfully!');
        }
      });
    }
  };

  const handleEdit = (page: CustomPage) => {
    setData({
      title: page.title,
      content: page.content,
      meta_title: page.meta_title || '',
      meta_description: page.meta_description || '',
      is_active: page.is_active,
      sort_order: page.sort_order || 0
    });
    setEditingPage(page);
  };

  const handleDelete = (page: CustomPage) => {
    setDeletingPage(page);
    setIsDeleteModalOpen(true);
  };

  const handleDeleteConfirm = () => {
    if (deletingPage) {
      router.delete(route('landing-page.custom-pages.destroy', deletingPage.id), {
        onSuccess: () => {
          setIsDeleteModalOpen(false);
          setDeletingPage(null);
          toast.success('Page deleted successfully!');
        },
        onError: () => {
          toast.error('Failed to delete page.');
        }
      });
    }
  };

  const resetForm = () => {
    reset();
    setEditingPage(null);
    setIsCreateOpen(false);
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    const params: any = { page: 1 };
    if (searchTerm) {
      params.search = searchTerm;
    }
    router.get(route('landing-page.custom-pages.index'), params, { preserveState: true, preserveScroll: true });
  };

  const handleAction = (action: string, item: CustomPage) => {
    if (action === 'edit') {
      handleEdit(item);
    } else if (action === 'delete') {
      handleDelete(item);
    }
  };

  const handleSort = (field: string) => {
    const direction = pageFilters.sort_field === field && pageFilters.sort_direction === 'desc' ? 'asc' : 'desc';
    const params: any = { 
      sort_field: field, 
      sort_direction: direction, 
      page: 1 
    };
    if (searchTerm) {
      params.search = searchTerm;
    }
    router.get(route('landing-page.custom-pages.index'), params, { preserveState: true, preserveScroll: true });
  };

  const columns = [
    { 
      key: 'title', 
      label: 'Title', 
      sortable: true,
      render: (value: string) => (
        <div className="font-medium">{value}</div>
      )
    },
    { 
      key: 'content', 
      label: 'Content',
      render: (value: string) => (
        <div className="max-w-xs truncate" title={value.replace(/<[^>]*>/g, '')}>
          {value.replace(/<[^>]*>/g, '').substring(0, 100)}...
        </div>
      )
    },
    { 
      key: 'is_active', 
      label: 'Status',
      render: (value: boolean) => (
        <div className="flex items-center space-x-1">
          {value ? (
            <><Eye className="w-4 h-4 text-green-600" /><span className="text-green-600">Active</span></>
          ) : (
            <><EyeOff className="w-4 h-4 text-gray-400" /><span className="text-gray-400">Inactive</span></>
          )}
        </div>
      )
    },
    { 
      key: 'created_at', 
      label: 'Created', 
      sortable: true,
      render: (value: string) => window.appSettings?.formatDateTime(value, false) || new Date(value).toLocaleDateString()
    }
  ];

  const actions = [
    { 
      label: 'Edit', 
      icon: 'Edit', 
      action: 'edit', 
      className: 'text-amber-500'
    },
    { 
      label: 'Delete', 
      icon: 'Trash2', 
      action: 'delete', 
      className: 'text-red-500'
    }
  ];

  return (
    <PageTemplate 
      title="Custom Pages" 
      url="/landing-page/custom-pages"
      breadcrumbs={[
        { title: 'Dashboard', href: route('dashboard') },
        { title: 'Custom Pages' }
      ]}
      actions={[
        {
          label: 'Add Page',
          icon: <Plus className="w-4 h-4 mr-2" />,
          variant: 'default',
          onClick: () => setIsCreateOpen(true)
        }
      ]}
      noPadding
    >
      {/* Search section */}
      <div className="bg-white dark:bg-gray-900 rounded-lg shadow mb-4 p-4">
        <SearchAndFilterBar
          searchTerm={searchTerm}
          onSearchChange={setSearchTerm}
          onSearch={handleSearch}
          filters={[]}
          showFilters={false}
          setShowFilters={() => {}}
          hasActiveFilters={() => false}
          activeFilterCount={() => 0}
          onResetFilters={() => {}}
          currentPerPage={pageFilters.per_page?.toString() || "10"}
          onPerPageChange={(value) => {
            const params: any = { page: 1, per_page: parseInt(value) };
            if (searchTerm) {
              params.search = searchTerm;
            }
            router.get(route('landing-page.custom-pages.index'), params, { preserveState: true, preserveScroll: true });
          }}
        />
      </div>

      {/* Table section */}
      <div className="bg-white dark:bg-gray-900 rounded-lg shadow overflow-hidden">
        <CrudTable
          columns={columns}
          actions={actions}
          data={pages?.data || pages || []}
          from={pages?.from || 1}
          onAction={handleAction}
          sortField={pageFilters.sort_field}
          sortDirection={pageFilters.sort_direction}
          onSort={handleSort}
        />

        {/* Pagination section */}
        {pages?.links && (
          <Pagination
            from={pages?.from || 0}
            to={pages?.to || 0}
            total={pages?.total || 0}
            links={pages?.links}
            entityName="pages"
            onPageChange={(url) => router.get(url)}
          />
        )}
      </div>

      {/* Edit Dialog */}
      <Dialog open={!!editingPage} onOpenChange={() => setEditingPage(null)}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>Edit Page</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmit} className="max-h-[80vh] overflow-y-auto space-y-4">
            <div>
              <Label htmlFor="edit_title">Page Title</Label>
              <Input
                id="edit_title"
                value={data.title}
                onChange={(e) => setData('title', e.target.value)}
                placeholder="About Us"
              />
              {errors.title && <p className="text-red-600 text-sm">{errors.title}</p>}
            </div>

            <div>
              <Label htmlFor="edit_content">Content</Label>
              <RichTextEditor
                content={data.content}
                onChange={(content) => setData('content', content)}
                placeholder="Page content..."
                className="min-h-[200px]"
              />
              {errors.content && <p className="text-red-600 text-sm">{errors.content}</p>}
            </div>

            <div>
              <Label htmlFor="edit_meta_title">Meta Title (SEO)</Label>
              <Input
                id="edit_meta_title"
                value={data.meta_title}
                onChange={(e) => setData('meta_title', e.target.value)}
                placeholder="SEO title"
              />
            </div>

            <div>
              <Label htmlFor="edit_meta_description">Meta Description (SEO)</Label>
              <Textarea
                id="edit_meta_description"
                value={data.meta_description}
                onChange={(e) => setData('meta_description', e.target.value)}
                placeholder="SEO description"
                rows={3}
              />
            </div>

            <div className="flex items-center space-x-2">
              <Switch
                id="edit_is_active"
                checked={data.is_active}
                onCheckedChange={(checked) => setData('is_active', checked)}
              />
              <Label htmlFor="edit_is_active">Active</Label>
            </div>

            <div className="flex justify-end space-x-2">
              <Button type="button" variant="outline" onClick={resetForm}>
                Cancel
              </Button>
              <Button type="submit" disabled={processing}>
                {processing ? 'Updating...' : 'Update Page'}
              </Button>
            </div>
          </form>
        </DialogContent>
      </Dialog>

      {/* Create Dialog */}
      <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
        <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>Create Custom Page</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <Label htmlFor="title">Page Title</Label>
              <Input
                id="title"
                value={data.title}
                onChange={(e) => setData('title', e.target.value)}
                placeholder="About Us"
              />
              {errors.title && <p className="text-red-600 text-sm">{errors.title}</p>}
            </div>

            <div>
              <Label htmlFor="content">Content</Label>
              <RichTextEditor
                content={data.content}
                onChange={(content) => setData('content', content)}
                placeholder="Page content..."
                className="min-h-[200px]"
              />
              {errors.content && <p className="text-red-600 text-sm">{errors.content}</p>}
            </div>

            <div>
              <Label htmlFor="meta_title">Meta Title (SEO)</Label>
              <Input
                id="meta_title"
                value={data.meta_title}
                onChange={(e) => setData('meta_title', e.target.value)}
                placeholder="SEO title"
              />
            </div>

            <div>
              <Label htmlFor="meta_description">Meta Description (SEO)</Label>
              <Textarea
                id="meta_description"
                value={data.meta_description}
                onChange={(e) => setData('meta_description', e.target.value)}
                placeholder="SEO description"
                rows={3}
              />
            </div>

            <div className="flex items-center space-x-2">
              <Switch
                id="is_active"
                checked={data.is_active}
                onCheckedChange={(checked) => setData('is_active', checked)}
              />
              <Label htmlFor="is_active">Active</Label>
            </div>

            <div className="flex justify-end space-x-2">
              <Button type="button" variant="outline" onClick={resetForm}>
                Cancel
              </Button>
              <Button type="submit" disabled={processing}>
                {processing ? 'Creating...' : 'Create Page'}
              </Button>
            </div>
          </form>
        </DialogContent>
      </Dialog>

      {/* Delete Modal */}
      <CrudDeleteModal
        isOpen={isDeleteModalOpen}
        onClose={() => {
          setIsDeleteModalOpen(false);
          setDeletingPage(null);
        }}
        onConfirm={handleDeleteConfirm}
        itemName={deletingPage?.title || ''}
        entityName="page"
      />

      <Toaster />
    </PageTemplate>
  );
}