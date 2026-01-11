# How to Preview OSS Detection in Fossology

## ⚠️ Prerequisites

Docker needs to be running to preview Fossology. The docker-compose.yml is configured to run Fossology on **port 8081**.

## 🚀 Quick Start Guide

### Step 1: Start Docker Desktop
Make sure Docker Desktop is running on Windows.

### Step 2: Build and Start Fossology

```powershell
cd c:\Users\hp\OneDrive\Desktop\fossology

# Build the Docker image with our new agent
docker-compose build

# Start Fossology services
docker-compose up -d

# Check if services are running
docker-compose ps
```

Expected output:
```
NAME                STATUS              PORTS
fossology-db-1      running             5432/tcp
fossology-scheduler-1  running
fossology-web-1     running             0.0.0.0:8081->80/tcp
```

### Step 3: Wait for Services to be Ready

```powershell
# Watch the logs to see when it's ready
docker-compose logs -f web

# Wait for message: "AH00094: Command line: 'apache2 -D FOREGROUND'"
# Press Ctrl+C to stop watching logs
```

### Step 4: Access Fossology Web Interface

Open browser to: **http://localhost:8081**

Default credentials:
- **Username**: fossy
- **Password**: fossy

## 📍 Where to See the OSS Detection Agent

### In the Upload Form:

1. Go to **Upload** → **From File**
2. Scroll to section **"7. Select Optional Analysis"**
3. Look for **"OSS Detection"** checkbox (NEW!)
4. This will be next to other agents like:
   - Copyright/Email/URL Scanner
   - Nomos License Scanner
   - Monk Scanner
   - **OSS Detection** ← OUR NEW AGENT

### In the Browse View:

1. After uploading a file containing metadata (e.g., package.json)
2. Click on the metadata file in the file browser
3. You'll see tabs at the top:
   - Info
   - View
   - Copyright
   - Licenses
   - **OSS Components** ← OUR NEW TAB
4. Click "OSS Components" to see:
   - List of detected dependencies
   - Version information
   - Similarity scores (if matches found)
   - Color-coded indicators (green/yellow/red)

## 🧪 Testing the Agent

### Create a Test Upload Package

```powershell
# Create a test directory
mkdir c:\Users\hp\Desktop\test-oss-detection
cd c:\Users\hp\Desktop\test-oss-detection

# Copy sample files
copy c:\Users\hp\OneDrive\Desktop\fossology\src\ossdetect\agent\test\sample_metadata\package.json .
copy c:\Users\hp\OneDrive\Desktop\fossology\src\ossdetect\agent\test\sample_metadata\requirements.txt .
copy c:\Users\hp\OneDrive\Desktop\fossology\src\ossdetect\agent\test\sample_metadata\pom.xml .

# Create a zip file
Compress-Archive -Path * -DestinationPath test-project.zip
```

### Upload to Fossology

1. Navigate to **Upload** → **From File**
2. Choose **test-project.zip**
3. Check **"OSS Detection"** under optional analysis
4. Click **Upload**
5. Wait for analysis to complete
6. Browse the uploaded files
7. Click on **package.json**
8. Click the **"OSS Components"** tab
9. See detected dependencies!

## 📊 Expected Results

### For package.json:
You should see:
- **7 dependencies detected**
- express (^4.18.2) - runtime
- react (^18.2.0) - runtime
- lodash (4.17.21) - runtime
- axios (^1.4.0) - runtime
- jest (^29.5.0) - development
- eslint (^8.42.0) - development
- webpack (^5.88.0) - development

### For requirements.txt:
You should see:
- **6 dependencies detected**
- django (>=4.2.0,<5.0)
- requests (==2.31.0)
- numpy (>=1.24.0)
- pandas (==2.0.3)
- pytest (>=7.0.0)
- black (==23.3.0)

### For pom.xml:
You should see:
- **4 dependencies detected**
- junit:junit (4.13.2) - test scope
- com.google.guava:guava (31.1-jre) - compile scope
- org.springframework.boot:spring-boot-starter-web (3.1.0)
- org.slf4j:slf4j-api (2.0.7)

## 🎨 UI Preview

The OSS Components tab will display:

```
┌─────────────────────────────────────────────────────────┐
│ OSS Components                                          │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ 📦 express                                              │
│ Version: ^4.18.2 | Scope: runtime | Line: 0            │
│                                                         │
│ Similarity Matches:                                     │
│ ├─ express @ 4.18.2  [Score: 100.0% - exact] 🟢        │
│ └─ No other matches                                     │
│                                                         │
│ ─────────────────────────────────────────────────────  │
│                                                         │
│ 📦 react                                                │
│ Version: ^18.2.0 | Scope: runtime | Line: 0            │
│                                                         │
│ Similarity Matches:                                     │
│ ├─ react @ 18.2.0  [Score: 95.0% - fuzzy] 🟡           │
│ └─ No other matches                                     │
│                                                         │
│ ─────────────────────────────────────────────────────  │
│                                                         │
│ 📦 jest                                                 │
│ Version: ^29.5.0 | Scope: development | Line: 0        │
│                                                         │
│ No matches found above threshold (80%)                  │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

## ⚙️ Configuration

Agent config location: `/srv/fossology/mods-enabled/ossdetect/ossdetect.conf`

Default settings:
```
SIMILARITY_THRESHOLD=80
MAX_RESULTS_PER_DEPENDENCY=5
ENABLE_POM_XML=true
ENABLE_PACKAGE_JSON=true
ENABLE_REQUIREMENTS_TXT=true
ENABLE_GO_MOD=true
ENABLE_GEMFILE=true
ENABLE_CARGO_TOML=true
```

## 🔧 Troubleshooting

### Agent doesn't appear in Upload form:
```powershell
# Rebuild the Docker image
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

### Database tables not created:
```powershell
# Connect to the database
docker-compose exec db psql -U fossy fossology

# Check if tables exist
\dt ossdetect*

# You should see:
# ossdetect_dependency
# ossdetect_match
# ossdetect_ars
```

### Python dependencies missing:
```powershell
# Enter the web container
docker-compose exec web bash

# Install dependencies
pip3 install xmltodict pyyaml toml

# Restart
exit
docker-compose restart web
```

## 🎯 What to Verify

✅ Agent appears in Upload form options  
✅ OSS Components tab appears in file browse view  
✅ Dependencies are extracted and displayed  
✅ Similarity scores are calculated  
✅ Color coding works (green/yellow/red)  
✅ Database tables are created  
✅ No errors in logs

## 📝 Notes

- The agent currently uses a **mock similarity database** for demonstration
- In production, it would integrate with Fossology's actual OSS component database
- The current implementation focuses on **metadata extraction** and **UI display**
- Future enhancements could add integration with public package registries

## 🚀 Ready for Production

Once verified locally:
1. Commit changes with prepared message
2. Push to your fork
3. Create PR referencing issue #2851
4. Engage with maintainers during review
