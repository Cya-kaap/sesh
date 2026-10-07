-- filename: sql/seed.sql
USE sesh2;
INSERT INTO role (role_name) VALUES ('Admin'),('Coordinator'),('Faculty');
INSERT INTO department (department_name) VALUES ('Computer Science'),('Business'),('Engineering');
INSERT INTO user (role_id,department_id,full_name,email,password_hash) VALUES (1,1,'System Administrator','admin@sesh.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC2f3K5w4U5p7cJ1Jm2');
INSERT INTO room (room_name,location,capacity) VALUES ('Room 101','Main Building',40),('Room 202','Science Block',30),('Lab A','Technology Wing',25);
INSERT INTO timeslot (slot_name,start_time,end_time) VALUES ('Period 1','08:00:00','09:00:00'),('Period 2','09:00:00','10:00:00'),('Period 3','10:00:00','11:00:00'),('Period 4','11:00:00','12:00:00'),('Period 5','13:00:00','14:00:00'),('Period 6','14:00:00','15:00:00');
INSERT INTO asset (room_id,asset_type,quantity) SELECT room_id,'Projector',1 FROM room;
INSERT INTO asset (room_id,asset_type,quantity) SELECT room_id,'AC',1 FROM room;
INSERT INTO asset (room_id,asset_type,quantity) SELECT room_id,'Whiteboard',1 FROM room;
-- Default admin password: password
