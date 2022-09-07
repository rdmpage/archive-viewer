# Simple Internet Archive viewer

A simple Internet Archive viewer. Displays pages in a scrollable  window, uses lazy loading (IntersectionObserver together with timeout to handle rapid scrolling). As each page is viewed it sends a message to the parent window with basic page information.

Viewer relies on local SQLite database that contains basic information about the item being displayed, such as sequence of pages, any page numbering or labelling information, and the dimensions of the page image.

## Annotation

Annotations, such as highlighting a block of text corresponding to a taxon name are stored in the `annotation` table. We store the sequence and (if present) page number where the annotation occurs, together with character-based coordinates and the actual string matched.
 