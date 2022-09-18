ALTER TABLE show1_entry ADD supportThreadID INT(10);
ALTER TABLE show1_entry ADD FOREIGN KEY (supportThreadID) REFERENCES wbb1_thread (threadID) ON DELETE SET NULL;
