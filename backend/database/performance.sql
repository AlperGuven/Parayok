-- MySQL Performance Optimization Settings
-- Run as: mysql -u root -p < database/performance.sql

-- Enable slow query log
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL slow_query_log_file = '/var/log/mysql/slow-query.log';
SET GLOBAL long_query_time = 2;
SET GLOBAL log_queries_not_using_indexes = 'ON';

-- Show current settings
SHOW VARIABLES LIKE 'slow_query_log%';
SHOW VARIABLES LIKE 'long_query_time%';
SHOW VARIABLES LIKE 'log_queries_not_using_indexes%';

-- Create index for common queries (if not exists)
-- Users table
CREATE INDEX idx_users_jira_cloud_id ON users(jira_cloud_id);

-- Room participants
CREATE INDEX idx_room_participants_room_id ON room_participants(room_id);
CREATE INDEX idx_room_participants_user_id ON room_participants(user_id);

-- Issues
CREATE INDEX idx_issues_room_id ON issues(room_id);
CREATE INDEX idx_issues_jira_issue_key ON issues(jira_issue_key);

-- Votes
CREATE INDEX idx_votes_issue_id ON votes(issue_id);
CREATE INDEX idx_votes_user_id ON votes(user_id);
