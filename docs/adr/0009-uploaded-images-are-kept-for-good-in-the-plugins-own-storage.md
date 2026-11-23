# Uploaded images are kept for good, in the plugin's own storage

An admin can upload images into Content. The plugin stores each Uploaded image in a Flysystem storage of its own, local in `public/media/terms` by default, under a name made from a hash of its bytes, and Content holds the URL that storage gives for it. Uploaded images are never deleted, not even ones that no Terms version uses. Terms versions are immutable and keep the URL of every image in their Content (ADR 0002), so deleting a file would change what an old version shows, and what the Terms PDF of every order that accepted it shows.

## Considered options

- **Sylius's `sylius.storage`.** Rejected: it has no public URL, so the plugin would have to assume `/media/image/`, which is wrong once an app moves that storage to S3 or a CDN.
- **A cleanup command that deletes images no Terms translation or Terms version refers to.** Rejected: unused images are rare and small, and their names can't be guessed without the image itself, so nobody finds them.
- **An entity per upload, so the admin can list and delete them.** Rejected: it needs a new table, and deleting one would still break the old versions that show it.

## Consequences

- An image that is uploaded and then left out of the saved Content stays in storage. An operator can delete it by hand.
- The same image uploaded twice is stored once, at the same URL.
- An app that moves the storage, for example to S3, has to keep the old URLs working, because Terms versions keep them.
- Images are stored as uploaded. Metadata such as a photo's EXIF location is kept.
- With the default storage, Content holds relative URLs, which work on every channel's hostname. In a Terms PDF, a relative image only shows when the channel has a hostname (ADR 0006).
