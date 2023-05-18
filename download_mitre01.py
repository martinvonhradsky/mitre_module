#!/usr/bin/env python
import os
import requests
import pandas as pd
import psycopg2
import logging
import time
from sqlalchemy import create_engine

# Define database credentials as environment variables
POSTGRES_DB = os.getenv('POSTGRES_DB')
POSTGRES_USER = os.getenv('POSTGRES_USER')
POSTGRES_PASSWORD = os.getenv('POSTGRES_PASSWORD')
DB_HOST = os.getenv('DB_HOST')

try:
    conn = psycopg2.connect(
        dbname=POSTGRES_DB,
        user=POSTGRES_USER,
        password=POSTGRES_PASSWORD,
        host=DB_HOST
    )
except psycopg2.Error as e:
    raise Exception(f"Could not connect to database: {e}")


#######################################################
# Creating table for storing 
def createTableTarget():
    try:
        # Create the tests table if it doesn't already exist
        conn.autocommit = True
        cur = conn.cursor()
        cur.execute("SELECT EXISTS (SELECT FROM pg_tables WHERE tablename = 'target');")
        table_exists = cur.fetchone()[0]

        # If table exists, drop it
        if table_exists:
            print("Database Target already exists. Nothing to do")

        else: # Else create DB
            print("Table doesn't exist: Creating table Target")
            cur.execute("""
                CREATE TABLE target (
                alias varchar(50) UNIQUE,
                IP INET NOT NULL,
                sudo_user VARCHAR(50) NOT NULL,
                password BYTEA NOT NULL,
                platform VARCHAR(20) NOT NULL
                );
            """)

            # insert dummy data for demonstration in UI
            cur.execute("""INSERT INTO target (ip, alias, sudo_user, password, platform) VALUES 
                ('192.168.1.101', 'gejza', 'admin', '4iW8&v^m$7', 'Linux'),
                ('192.168.1.102', 'buk', 'root', 'G!2sD7@fP9', 'Windows'),
                ('192.168.1.103', 'smajdova mamina', 'sysadmin', '9Kt5#eX1!j', 'FreeBSD'),
                ('192.168.1.104', 'himmler', 'administrator', '3nB6$rV0%l', 'MacOS'),
                ('192.168.1.105', 'ritmuskinpopo', 'user1', '8qC4&pU6@z', 'Ubuntu');
            """)
        cur.close()
        return 1
    except Exception as e:
        logging.error("Error: %s", e)
        return 0


#######################################################
# Creating table for storing Test history 
def createTableHistory():
    try:
        # Create the tests table if it doesn't already exist
        conn.autocommit = True
        cur = conn.cursor()
        cur.execute("SELECT EXISTS (SELECT FROM pg_tables WHERE tablename = 'history');")
        table_exists = cur.fetchone()[0]

        # If table exists, drop it
        if table_exists:
            print("Database History already exists. Nothing to do")

        else: # Else create DB
            print("Table doesn't exist: Creating table History")
            cur.execute('''
                CREATE TABLE history (
                    test_id TEXT,
                    target TEXT,
                    start_time TEXT,
                    end_time TEXT,
                    output TEXT,
                    detected BOOLEAN
                )
            ''')

        cur.close()
        return 1
    except Exception as e:
        logging.error("Error: %s", e)
        return 0


#######################################################
# Creating table for storing Test history 
def insertHistory():
    try:
        # Create the tests table if it doesn't already exist
        conn.autocommit = True
        cur = conn.cursor()
        print("doing shit")
        # Insert sample rows
        cur.execute('''
            INSERT INTO history (test_id, target, start_time, end_time, output, detected) 
            VALUES ('T1548', 'example.com', '2023-05-08 12:00:00', '2023-05-08 12:01:00', 'success', true),
                   ('T1548', 'google.com', '2023-05-08 12:02:00', '2023-05-08 12:03:00', 'failed', true),
                   ('T1548', 'yahoo.com', '2023-05-08 12:04:00', '2023-05-08 12:05:00', 'success', false),
                   ('T1548', 'bing.com', '2023-05-08 12:06:00', '2023-05-08 12:07:00', 'failed', false),
                   ('T1548', 'amazon.com', '2023-05-08 12:08:00', '2023-05-08 12:09:00', 'success', true),
                   ('T1548', 'wikipedia.org', '2023-05-08 12:10:00', '2023-05-08 12:11:00', 'success', false),
                   ('T1548', 'linkedin.com', '2023-05-08 12:12:00', '2023-05-08 12:13:00', 'failed', false),
                   ('T1548', 'facebook.com', '2023-05-08 12:14:00', '2023-05-08 12:15:00', 'success', true),
                   ('T1548', 'twitter.com', '2023-05-08 12:16:00', '2023-05-08 12:17:00', 'failed', true),
                   ('T1548', 'instagram.com', '2023-05-08 12:18:00', '2023-05-08 12:19:00', 'success', false)
        ''')

        cur.close()
        return 1
    except Exception as e:
        logging.error("Error: %s", e)
        return 0


##############################################################
# Initialize Tests table for InvokeAtomic and custom user test
# In first run - DB created and populated with InvokeAtomic data
# Rerun works as update: custom tests stay in DB while Atomics get erased and repopulated
def createTableTests():
    try:
        # Create the tests table if it doesn't already exist
        conn.autocommit = True
        cur = conn.cursor()
        cur.execute("SELECT EXISTS (SELECT FROM pg_tables WHERE tablename = 'tests');")
        table_exists = cur.fetchone()[0]

        # If table exists, drop it
        if table_exists:
            print("Database Tests already exists. Dropping Atomics")
            cur.execute("DELETE FROM tests WHERE executable='Invoke atomic'")

        else: # Else create DB
            print("Table doesn't exist: Creating table Test")
            cur.execute("""
                CREATE TABLE IF NOT EXISTS tests (
                    id SERIAL PRIMARY KEY,
                    technique_id VARCHAR(15) NOT NULL,
                    test_number INTEGER NOT NULL,
                    name TEXT NOT NULL,
                    file_name TEXT,
                    executable TEXT NOT NULL,
                    local_execution BOOLEAN,                    
                    description TEXT
                )
            """)
        cur.close()
        parseDataToDB()
        return 1
    except Exception as e:
        logging.error("Error: %s", e)
        return 0

def makeColoring():
    conn.autocommit = True
    cur = conn.cursor()
    try:
        cur.execute("SELECT id FROM mitre WHERE id NOT LIKE '%.%';")
        test_available = cur.fetchall()
        for test in test_available:
            test_id1 = f"{test[0]}"
            test_id2 = f"{test[0]}.%"
            cur.execute(query="SELECT status FROM mitre WHERE id LIKE %s OR id LIKE %s ;", vars=(test_id1,test_id2))
            result = cur.fetchall()
            status = "not available"
            for row in result:
                if "detected" in row: 
                    status = "detected" 
                    break
                if "executed" in row and status != "detected":
                    status = "executed"
                else: 
                    if "available" in row and status != "available":
                        status = "available"
            cur.execute("UPDATE mitre SET startpage = %s WHERE id = %s ", vars=(status, test[0]))
            
    except Exception as e:
        logging.error(f"Error in coloring stratpage MitreDB: {e}")
    logging.info("Flagging MitreDB finished")

def flagMitreDB(df):
    logging.info("Flagging MitreDB started")
    df['status'] = 'not available'
    df['startpage'] = 'no action'
    conn.autocommit = True
    cur = conn.cursor()
    for index, row in df.iterrows():
        try:
            cur.execute("SELECT EXISTS (SELECT FROM tests WHERE technique_id = %s);", (row.id,))
            test_available = cur.fetchone()[0]
            if test_available:
                df.at[index, 'status'] = 'available'
        except Exception as e:
            logging.error(f"Error in flagging MitreDB: {e}")
    logging.info("Flagging MitreDB finished")
    cur.close()
    return df


def parseDataToDB():
    logging.info("Parsing started")
    # get the directory containing the script
    dir_path = os.path.dirname(os.path.realpath(__file__))

    # read the data from the file
    with open(os.path.join('/data', 'available_tests.txt'), 'r') as file:
        data = file.read()
        data = data[:len(data) - 4].split("\", \"")

    # create a new array to store Tests from InvokeAtomic
    techniques = []

    # iterate through each line in the data
    for line in data:
        # check if the line starts with "T" (indicating a technique)
        if line.startswith("T"):
            # append the technique to the techniques array
            techniques.append(line)
    if not techniques:
        logging.warning("No techniques available => no file")
    
    # Parse the data and insert it into the tests table
    for item in techniques:
        # Extract the technique ID and test number from the item
        technique_id = item.split("-")[0]
        test_number = item.split("-")[1].split(" ")[0]
        
        # Extract the name of the test
        name = item.split(" ",1)[1].strip()
        
        # Insert the data into the tests table
        try:
            cur = conn.cursor()
            cur.execute(
                "INSERT INTO tests (technique_id, test_number, name, executable) VALUES (%s, %s, %s, %s)",
                (technique_id, test_number, name, "Invoke atomic")
            )
            conn.commit()
        except Exception as e:
            logging.error(f"Failed to insert data into the database: {str(e)}")
    cur.close()
    logging.info("Parsing finished")


    

# Download Mitre matrix
def downloadMatrix():
    URL = "https://attack.mitre.org/docs/enterprise-attack-v13.1/enterprise-attack-v13.1.xlsx"
    # download the data behind the URL
    response = requests.get(URL)
    # Open the response into a new file
    open("matrix.xlsx", "wb").write(response.content)

def createDatabase():
    # Sleep to ensure databese is already setup    
    time.sleep(10)
    # Create database connection

    conn.autocommit = True
    cursor = conn.cursor()

    # Check if table already exists
    cursor.execute("SELECT EXISTS (SELECT FROM pg_tables WHERE tablename = 'mitre');")
    table_exists = cursor.fetchone()[0]

    # If table exists, drop it
    if table_exists:
        print("Table already exists. Dropping table 'mitre'...")
        cursor.execute("DROP TABLE mitre;")
        print("Table mitre dropped successfully.")

    # Check if the file exists
    if os.path.exists('./matrix.xlsx'):
        print("createDatabase(): using local file matrix.xlsx ")
    else:
        print("createDatabase(): no local file matrix.xlsx available. Downloading...")
        downloadMatrix()
        

    # Create new table and insert data
    df = pd.read_excel(r'./matrix.xlsx', engine="openpyxl")
    df = df[['ID', 'name', 'description', 'url', 'tactics', 'platforms']]
    logging.warning('postgresql://' + POSTGRES_USER + ':' + POSTGRES_PASSWORD + '@' + DB_HOST + '/' + POSTGRES_DB)
    engine = create_engine(f'postgresql://{POSTGRES_USER}:{POSTGRES_PASSWORD}@{DB_HOST}/{POSTGRES_DB}')
    df.columns = df.columns.str.lower()
    df = flagMitreDB(df)
    df.to_sql('mitre', engine, if_exists='replace')

    cursor.close()
    
    #os.remove('matrix.xlsx')

if __name__ == '__main__':
    try:
        createTableHistory()
        #insertHistory()
        createTableTarget()
        createTableTests()
        print('Downloading MITRE data.')
        #download_matrix()
        print('Pushing to DB.')
        createDatabase()
        print('Coloring')
        makeColoring()
        print('Done.')
        conn.close()
    except Exception as e:
        print(f"An error occurred: {e}")
