#!/usr/bin/env python3
import os
import sys
import json
import pika
from faker import Faker

# RabbitMQ connection parameters from environment variables or defaults
_host = os.getenv("RABBITMQ_HOST", "localhost")
_port = int(os.getenv("RABBITMQ_PORT", 5672))
_user = os.getenv("RABBITMQ_USER", "guest")
_password = os.getenv("RABBITMQ_PASSWORD", "guest")
_vhost = os.getenv("RABBITMQ_VHOST", "/")

_credentials = pika.PlainCredentials(_user, _password)
_params = pika.ConnectionParameters(
    host=_host, port=_port, credentials=_credentials, virtual_host=_vhost
)

connection = pika.BlockingConnection(_params)
channel = connection.channel()
queue_name = 'wordpress_updates'

# Ensure the queue exists
channel.queue_declare(queue=queue_name, durable=True)

def generate_user():
    fake = Faker()
    message = {
        "action": "create",
        "email": fake.email(),
        "values": {
            "firstname": fake.first_name(),
            "lastname": fake.last_name(),
            "phone": fake.phone_number(),
            "business": fake.company(),
            "date_of_birth": fake.date_of_birth().isoformat()
        }
    }
    return json.dumps(message)

def generate_business():
    fake = Faker()
    message = {
        "action": "create",
        "email": fake.email(),
        "values": {
            "business_name": fake.company(),
            "VAT": fake.random_number(digits=9),
            "access_code": fake.random_number(digits=4),
            "address": fake.address()
        }
    }
    return json.dumps(message)

def reuse_existing_json():
    with open(XML_OUTPUT, "r") as file:
        return file.read()

def usage():
    print("""usage: publish.py COMMAND
COMMANDS
    user                        send random user create
    business                    send random business create
    reuse                       send content from a previous JSON file""")
    exit(1)

if len(sys.argv) < 2:
    usage()

if sys.argv[1] == "user":
    message = generate_user()
elif sys.argv[1] == "business":
    message = generate_business()
elif sys.argv[1] == "reuse":
    message = reuse_existing_json()
else:
    usage()

channel.basic_publish(
    exchange='',
    routing_key=queue_name,
    body=message,
    properties=pika.BasicProperties(
        delivery_mode=2,  # Make the message persistent
    )
)

# Optionally, save the last message for reuse
with open(XML_OUTPUT, "w") as file:
    file.write(message)

print(f"Sent message to '{queue_name}':\n{message}")
connection.close()
