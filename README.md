# PHP Host Pro

Real PHP hosting panel using PHP 8.3, Apache, Docker and Render.

Features:
- Upload PHP ZIP projects
- Real PHP execution
- Automatic project URL
- Project list/delete
- API authentication
- ZIP path security
- 50MB upload configuration

Project URL:
/sites/PROJECT_NAME/

API:
GET /api.php?action=health
POST /api.php?action=upload
GET /api.php?action=projects
DELETE /api.php?action=delete&project=PROJECT_NAME

Protected API endpoints require:
X-API-Key: YOUR_HOSTING_API_KEY

Important:
Uploaded PHP code executes on the same server. Only allow trusted users to upload PHP projects.

Render Free Web Services use ephemeral filesystem storage. For permanent uploaded projects, use a paid service with a persistent disk mounted at /var/www/html/sites.
