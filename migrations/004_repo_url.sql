-- Add repo_url to settings if it doesn't already exist
ALTER TABLE settings
  ADD COLUMN IF NOT EXISTS repo_url VARCHAR(255);
