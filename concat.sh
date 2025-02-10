#!/usr/bin/env bash

# Schimbă aici calea unde vrei să salvezi fișierul final.
TARGET="/home/src21/cod.txt"

# Golește sau creează fișierul final.
> "$TARGET"

# Parcurge toate fișierele (fără directoare) din folderul curent, recursiv.
# Poți exclude anumite directoare sau fișiere dacă vrei (vezi `-prune`).
find . -type f \
     -not -path "./.git/*" \
     -not -name "concat.sh" \
     -print | while read -r file; do
  # Scrie un header înainte de conținutul fiecărui fișier
  echo "========================================" >> "$TARGET"
  echo "FILE: $file" >> "$TARGET"
  echo "========================================" >> "$TARGET"
  
  # Copiază conținutul fișierului
  cat "$file" >> "$TARGET"
  
  # Linie goală de separare
  echo "" >> "$TARGET"
done

echo "Conținut concatenat în $TARGET"
