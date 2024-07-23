# roco_z21_dispatcher for restaurant
Software for dispatching trains

This software is used to dispatchering roco trains via Z21 controller. 
HTML folder contains example of web interface for dispatchering 3 trains (2 trains + 1 train)

codes.ini is a map with predefined codes for every digital train: 
- moving forward with different speeds
- moving backward with different speeds
- stop
- railway arrow stitch number on/off
- additional functions for trains (light, sounds etc)

codes.ini defines which command to send via Z21 when various feedbacks happens

How to start:
php client.php, open http://127.0.0.1 in browser, click the place the train should go.
